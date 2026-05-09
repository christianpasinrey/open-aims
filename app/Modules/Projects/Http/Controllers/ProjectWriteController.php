<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mutations on projects from the repo-style UI: create + partial update +
 * milestone create. Workspace scoping is applied via the global
 * BelongsToWorkspace scope on Project; we still resolve the active workspace
 * to know where to attach new rows. No granular permissions yet — auth +
 * verified middleware on the route group is the gate.
 */
final class ProjectWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'team_keys' => 'nullable|array',
            'team_keys.*' => 'string|max:16',
            'lead_user_id' => 'nullable|integer|exists:users,id',
            'state' => 'nullable|in:backlog,planned,started,paused,completed,canceled',
            'color' => 'nullable|string|max:9',
            'icon' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        $teamKeys = collect($data['team_keys'] ?? [])
            ->map(static fn (string $k): string => strtoupper($k))
            ->unique()
            ->values()
            ->all();

        $teamIds = [];
        if ($teamKeys !== []) {
            $teamIds = Team::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('key', $teamKeys)
                ->pluck('id')
                ->all();
        }

        $project = DB::transaction(function () use ($workspace, $data, $teamIds): Project {
            $slug = $this->uniqueSlug($workspace->id, $data['name']);

            $state = $data['state'] ?? 'backlog';
            $completedAt = in_array($state, ['completed', 'canceled'], true) ? now() : null;

            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'state' => $state,
                'lead_user_id' => $data['lead_user_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'target_date' => $data['target_date'] ?? null,
                'color' => $data['color'] ?? '#6366f1',
                'icon' => $data['icon'] ?? null,
                'completed_at' => $completedAt,
            ]);

            if ($teamIds !== []) {
                $project->teams()->sync($teamIds);
            }

            return $project;
        });

        return redirect()->route('projects.show', ['slug' => $project->slug]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'lead_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'state' => 'sometimes|in:backlog,planned,started,paused,completed,canceled',
            'priority' => 'sometimes|integer|in:0,1,2,3,4',
            'color' => 'sometimes|string|max:9',
            'icon' => 'sometimes|nullable|string|max:64',
            'start_date' => 'sometimes|nullable|date',
            'target_date' => 'sometimes|nullable|date',
            'completed_at' => 'sometimes|nullable|date',
        ]);

        // Auto-manage completed_at on state transitions unless the caller
        // sent its own value explicitly.
        if (array_key_exists('state', $data) && ! array_key_exists('completed_at', $data)) {
            $newState = $data['state'];
            if (in_array($newState, ['completed', 'canceled'], true)) {
                if ($project->completed_at === null) {
                    $data['completed_at'] = now();
                }
            } else {
                $data['completed_at'] = null;
            }
        }

        $project->fill($data)->save();

        return back();
    }

    public function storeMilestone(Request $request, string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);

        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $sortOrder = $data['sort_order'] ?? null;
        if ($sortOrder === null) {
            $sortOrder = ((int) ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->max('sort_order')) + 1;
        }

        ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'sort_order' => $sortOrder,
        ]);

        return back();
    }

    /**
     * Soft-delete a project together with its issues and milestones.
     *
     * Items are stamped with the same deleted_at so we can restore the
     * exact set the user trashed, even if other items were soft-deleted
     * independently later.
     */
    public function destroy(string $slug): RedirectResponse
    {
        $project = $this->resolveProject($slug);
        $now = now();

        DB::transaction(function () use ($project, $now): void {
            Issue::query()
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $project->deleted_at = $now;
            $project->save();
        });

        return redirect()->route('projects.index');
    }

    public function restore(string $slug): RedirectResponse
    {
        $project = $this->resolveTrashedProject($slug);

        if ($project->deleted_at === null) {
            return redirect()->route('projects.show', ['slug' => $slug]);
        }

        $deletedAt = Carbon::parse($project->deleted_at);
        // Match the cascade we did at delete-time. ±1 second tolerance covers
        // the (tiny) window where update() and save() may differ by ms.
        $from = $deletedAt->copy()->subSecond();
        $to = $deletedAt->copy()->addSecond();

        DB::transaction(function () use ($project, $from, $to): void {
            Issue::query()
                ->withoutGlobalScopes()
                ->where('project_id', $project->id)
                ->whereBetween('deleted_at', [$from, $to])
                ->update(['deleted_at' => null]);

            ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->whereBetween('deleted_at', [$from, $to])
                ->update(['deleted_at' => null]);

            $project->deleted_at = null;
            $project->save();
        });

        return redirect()->route('projects.show', ['slug' => $slug]);
    }

    public function forceDestroy(string $slug): RedirectResponse
    {
        $project = $this->resolveTrashedProject($slug);
        $project->forceDelete();

        return redirect()->route('trash.index');
    }

    private function resolveProject(string $slug): Project
    {
        $workspace = $this->workspace();

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->first();
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    private function resolveTrashedProject(string $slug): Project
    {
        $workspace = $this->workspace();

        $project = Project::query()
            ->withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->first();
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    private function uniqueSlug(int $workspaceId, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'project';
        }
        // repo-style: keep the human slug stable but disambiguate per workspace
        // with a 6-char random suffix. Loop on collision (very rare).
        for ($i = 0; $i < 5; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            $exists = Project::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->where('slug', $slug)
                ->exists();
            if (! $exists) {
                return $slug;
            }
        }

        // Last-resort fallback: timestamp suffix
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
