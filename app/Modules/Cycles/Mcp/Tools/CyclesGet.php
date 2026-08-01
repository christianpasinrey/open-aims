<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Fetch a cycle (a sprint) with everything needed for sprint review and planning '
    .'in one call: progress totals (total/started/completed), a per-assignee breakdown, '
    .'and `issues` — the issues committed to the cycle as '
    .'{identifier, title, state, estimate, assignee}. '
    .'Cycles are addressed as (team_key, number); list them with `cycles-list`. '
    .'Move issues in or out with `issues-update` and its `cycle_number` parameter.'
)]
class CyclesGet extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'team_key' => 'required|string|max:16',
            'number' => 'required|integer|min:1',
        ])->validate();

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found.");
        }

        $cycle = Cycle::query()
            ->where('team_id', $team->id)
            ->where('number', (int) $data['number'])
            ->first();
        if ($cycle === null) {
            return Response::error("Cycle {$data['number']} not found in {$team->key}.");
        }

        $issues = Issue::query()
            ->where('cycle_id', $cycle->id)
            ->whereNull('archived_at')
            ->with([
                'workflowState:id,name,type,position',
                'assignee:id,name',
                'project:id,name',
                'labels:id,name',
                'team:id,key',
            ])
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        $total = $issues->count();
        $completed = $issues->filter(fn ($i) => $i->workflowState?->type === 'completed')->count();
        $started = $issues->filter(fn ($i) => $i->workflowState?->type === 'started')->count();

        $assigneeBreakdown = $issues->groupBy(fn ($i) => $i->assignee?->id ?? 0)
            ->map(fn ($g) => [
                'name' => $g->first()->assignee?->name ?? 'Unassigned',
                'total' => $g->count(),
                'completed' => $g->filter(fn ($i) => $i->workflowState?->type === 'completed')->count(),
            ])->values();

        $now = now();
        $isCurrent = $cycle->starts_at !== null
            && $cycle->ends_at !== null
            && $cycle->starts_at->lte($now)
            && $cycle->ends_at->gte($now);

        return Response::json([
            'team_key' => $team->key,
            'number' => $cycle->number,
            'name' => $cycle->name,
            'description' => $cycle->description,
            'starts_at' => $cycle->starts_at?->toDateString(),
            'ends_at' => $cycle->ends_at?->toDateString(),
            'completed_at' => $cycle->completed_at?->toIso8601String(),
            'is_current' => $isCurrent,
            'progress' => [
                'total' => $total,
                'started' => $started,
                'completed' => $completed,
                'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ],
            'assignees' => $assigneeBreakdown,
            'issues' => $issues->map(fn (Issue $i) => [
                'identifier' => ($i->team?->key ?? $team->key).'-'.$i->number,
                'title' => $i->title,
                'state' => $i->workflowState?->name,
                'estimate' => $i->estimate,
                'assignee' => $i->assignee?->name,
            ])->values()->all(),
            'url' => "/cycles/{$cycle->number}?team={$team->key}",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required(),
            'number' => $schema->integer()->required()->description('Cycle (sprint) number within the team.'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
