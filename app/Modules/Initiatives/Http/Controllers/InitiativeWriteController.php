<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Http\Controllers;

use App\Modules\Initiatives\Models\Initiative;
use App\Modules\Projects\Models\Project;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InitiativeWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'state' => 'nullable|in:planned,active,completed,canceled',
            'owner_user_id' => 'nullable|integer|exists:users,id',
            'parent_initiative_id' => 'nullable|integer',
            'color' => 'nullable|string|max:9',
            'icon' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        $state = $data['state'] ?? 'planned';
        $completedAt = in_array($state, ['completed', 'canceled'], true) ? now() : null;

        // Validate parent belongs to same workspace
        $parentId = $data['parent_initiative_id'] ?? null;
        if ($parentId !== null) {
            $parent = Initiative::query()
                ->where('workspace_id', $workspace->id)
                ->where('id', $parentId)
                ->first();
            if ($parent === null) {
                $parentId = null;
            }
        }

        $initiative = Initiative::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($workspace->id, $data['name']),
            'description' => $data['description'] ?? null,
            'state' => $state,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'parent_initiative_id' => $parentId,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'completed_at' => $completedAt,
        ]);

        return redirect()->route('initiatives.show', ['slug' => $initiative->slug]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $initiative = $this->resolve($slug);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'state' => 'sometimes|in:planned,active,completed,canceled',
            'owner_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'parent_initiative_id' => 'sometimes|nullable|integer',
            'color' => 'sometimes|string|max:9',
            'icon' => 'sometimes|nullable|string|max:64',
            'start_date' => 'sometimes|nullable|date',
            'target_date' => 'sometimes|nullable|date',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        if (array_key_exists('state', $data) && ! array_key_exists('completed_at', $data)) {
            $newState = $data['state'];
            if (in_array($newState, ['completed', 'canceled'], true)) {
                if ($initiative->completed_at === null) {
                    $data['completed_at'] = now();
                }
            } else {
                $data['completed_at'] = null;
            }
        }

        if (array_key_exists('parent_initiative_id', $data) && $data['parent_initiative_id'] !== null) {
            $parent = Initiative::query()
                ->where('workspace_id', $initiative->workspace_id)
                ->where('id', $data['parent_initiative_id'])
                ->first();
            if ($parent === null || $parent->id === $initiative->id) {
                $data['parent_initiative_id'] = null;
            }
        }

        $initiative->fill($data)->save();

        return back();
    }

    public function attachProject(Request $request, string $slug): RedirectResponse
    {
        $initiative = $this->resolve($slug);

        $data = $request->validate([
            'project_id' => 'required|integer',
        ]);

        $project = Project::query()
            ->where('workspace_id', $initiative->workspace_id)
            ->where('id', $data['project_id'])
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        // syncWithoutDetaching keeps existing rows; pivot has sort_order.
        $maxOrder = (int) $initiative->projects()
            ->withPivot('sort_order')
            ->max('initiative_projects.sort_order');

        $initiative->projects()->syncWithoutDetaching([
            $project->id => ['sort_order' => $maxOrder + 1],
        ]);

        return back();
    }

    public function detachProject(string $slug, int $project): RedirectResponse
    {
        $initiative = $this->resolve($slug);

        $initiative->projects()->detach($project);

        return back();
    }

    private function resolve(string $slug): Initiative
    {
        $workspace = $this->workspace();

        $initiative = Initiative::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->first();
        if ($initiative === null) {
            throw new NotFoundHttpException('Initiative not found.');
        }

        return $initiative;
    }

    private function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'initiative';
        }
        for ($i = 0; $i < 5; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            $exists = Initiative::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $slug)
                ->exists();
            if (! $exists) {
                return $slug;
            }
        }

        return $base.'-'.now()->format('YmdHis');
    }

    private function workspace(): Workspace
    {
        if (! app()->bound('current.workspace')) {
            abort(404, 'No active workspace.');
        }
        $w = app('current.workspace');
        if (! $w instanceof Workspace) {
            abort(404, 'No active workspace.');
        }

        return $w;
    }
}
