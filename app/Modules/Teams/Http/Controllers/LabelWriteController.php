<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Labels are scoped to a team. This controller exposes the small CRUD surface
 * used by the issue/project label pickers (quick-create) and the label
 * management page.
 */
final class LabelWriteController
{
    public function index(string $key): Response
    {
        $team = $this->resolveTeam($key);

        $labels = Label::query()
            ->where('team_id', $team->id)
            ->withCount('issues')
            ->orderBy('name')
            ->get();

        return Inertia::render('teams/Labels', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'labels' => $labels->map(static fn (Label $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
                'description' => $l->description,
                'issues_count' => (int) ($l->issues_count ?? 0),
            ])->all(),
        ]);
    }

    public function store(Request $request, string $key): JsonResponse|RedirectResponse
    {
        $team = $this->resolveTeam($key);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'color' => 'sometimes|string|max:9',
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        // De-dupe on (team_id, name) to honour the unique index.
        $label = Label::query()->firstOrCreate(
            ['team_id' => $team->id, 'name' => $data['name']],
            [
                'color' => $data['color'] ?? '#64748b',
                'description' => $data['description'] ?? null,
            ],
        );

        // Pickers call this with Accept: application/json and want the new
        // row inline. The management page submits via Inertia and expects a
        // server-side redirect back to the labels list.
        if ($request->wantsJson()) {
            return response()->json([
                'id' => $label->id,
                'team_id' => $label->team_id,
                'name' => $label->name,
                'color' => $label->color,
            ], 201);
        }

        return back();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $label = $this->resolveLabel($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'color' => 'sometimes|string|max:9',
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        $label->fill($data)->save();

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $label = $this->resolveLabel($id);
        $label->delete();

        return back();
    }

    private function resolveTeam(string $key): Team
    {
        $workspace = $this->workspace();
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($key))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        return $team;
    }

    private function resolveLabel(int $id): Label
    {
        $workspace = $this->workspace();
        $label = Label::query()
            ->whereHas('team', static fn ($q) => $q->where('workspace_id', $workspace->id))
            ->where('id', $id)
            ->first();
        if ($label === null) {
            throw new NotFoundHttpException('Label not found.');
        }

        return $label;
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
