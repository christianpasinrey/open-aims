<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Everything a client needs to draw a workspace sidebar, in one call: the workspace, the caller, '
    .'the other workspaces the caller can switch to, every team with its workflow states (id, name, '
    .'type, colour, position) and its active cycle, and every project with its progress. Use '
    .'`current` instead when you only need to orient before a write.'
)]
class BoardOverview extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }
        $user = auth()->user();

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->with(['workflowStates' => fn ($q) => $q->orderBy('position')->orderBy('id')])
            ->orderBy('name')
            ->get();

        $activeCycles = $this->activeCyclesByTeam($teams->modelKeys());

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->with('teams:id,key')
            ->withCount([
                'issues as total_issues',
                'issues as completed_issues' => fn ($q) => $q->whereHas(
                    'workflowState',
                    fn ($w) => $w->where('type', 'completed'),
                ),
            ])
            ->orderBy('name')
            ->get();

        return Response::json([
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ],
            'me' => [
                'id' => $user?->getAuthIdentifier(),
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'workspaces' => $this->reachableWorkspaces($workspace),
            'teams' => $teams->map(fn (Team $team): array => [
                'id' => $team->id,
                'key' => $team->key,
                'name' => $team->name,
                'color' => $team->color,
                'icon' => $team->icon,
                'states' => $team->workflowStates->map(fn (WorkflowState $state): array => [
                    'id' => $state->id,
                    'name' => $state->name,
                    'type' => $state->type,
                    'color' => $state->color,
                    'position' => $state->position,
                ])->values()->all(),
                'active_cycle' => $this->cycleRow($activeCycles->get($team->id)),
            ])->all(),
            'projects' => $projects->map(function (Project $project): array {
                $total = (int) $project->total_issues;
                $completed = (int) $project->completed_issues;

                return [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'state' => $project->state?->value,
                    'color' => $project->color,
                    'icon' => $project->icon,
                    'team_keys' => $project->teams->pluck('key')->values()->all(),
                    'total_issues' => $total,
                    'completed_issues' => $completed,
                    'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                ];
            })->all(),
        ]);
    }

    /**
     * The cycle each team is in right now (what `cycles-list` calls
     * `current`), keyed by team id; the latest start wins if two overlap.
     *
     * @param  array<int, int>  $teamIds
     * @return Collection<int, Cycle>
     */
    private function activeCyclesByTeam(array $teamIds): Collection
    {
        $now = now();

        return Cycle::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('completed_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderBy('starts_at')
            ->get()
            ->keyBy('team_id');
    }

    /**
     * @return array{number:int,name:?string,starts_at:?string,ends_at:?string}|null
     */
    private function cycleRow(?Cycle $cycle): ?array
    {
        if ($cycle === null) {
            return null;
        }

        return [
            'number' => $cycle->number,
            'name' => $cycle->name,
            'starts_at' => $cycle->starts_at?->toIso8601String(),
            'ends_at' => $cycle->ends_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{slug:string,name:string,is_active:bool}>
     */
    private function reachableWorkspaces(Workspace $active): array
    {
        return Workspace::query()
            ->whereIn('id', WorkspaceMember::query()
                ->where('user_id', auth()->user()?->getAuthIdentifier())
                ->select('workspace_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Workspace $w): array => [
                'slug' => $w->slug,
                'name' => $w->name,
                'is_active' => (int) $w->id === (int) $active->id,
            ])
            ->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string()
                ->description('Workspace slug. Omit only when the caller belongs to a single workspace.'),
        ];
    }
}
