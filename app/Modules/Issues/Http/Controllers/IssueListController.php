<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IssueListController
{
    public function index(Request $request): Response
    {
        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;
        $assigneeFilter = $request->query('assignee');
        $assigneeFilter = is_string($assigneeFilter) && $assigneeFilter !== '' ? strtolower($assigneeFilter) : null;
        $stateFilter = $request->query('state');
        $stateFilter = is_string($stateFilter) && $stateFilter !== '' ? strtolower($stateFilter) : null;

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return $this->emptyResponse($assigneeFilter, $stateFilter);
        }

        $teamQuery = Team::query()->where('workspace_id', $workspace->id);
        $team = $teamKey !== null
            ? $teamQuery->where('key', $teamKey)->first()
            : $teamQuery->orderBy('name')->first();

        if ($team === null) {
            return $this->emptyResponse($assigneeFilter, $stateFilter, $teamKey);
        }

        $states = WorkflowState::query()
            ->where('team_id', $team->id)
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position']);

        $issuesQuery = Issue::query()
            ->where('team_id', $team->id)
            ->whereNull('archived_at');

        if ($assigneeFilter === 'me') {
            $issuesQuery->where('assignee_user_id', (int) $request->user()?->getKey());
        } elseif ($assigneeFilter === 'unassigned') {
            $issuesQuery->whereNull('assignee_user_id');
        }

        if ($stateFilter !== null) {
            $issuesQuery->whereHas(
                'workflowState',
                static fn ($q) => $q->where('type', $stateFilter),
            );
        }

        $issues = $issuesQuery
            ->with([
                'assignee:id,name,email',
                'creator:id,name,email',
                'workflowState:id,name,type,color,position',
                'labels:id,name,color',
                'project:id,name,slug,color,icon',
            ])
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        return Inertia::render('issues/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'states' => $states->map(fn (WorkflowState $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
                'position' => $s->position,
            ])->all(),
            'issues' => $issues->map(fn (Issue $i): array => [
                'id' => $i->id,
                'identifier' => $team->key.'-'.$i->number,
                'number' => $i->number,
                'title' => $i->title,
                'priority' => (int) ($i->priority?->value ?? 0),
                'state_id' => $i->workflow_state_id,
                'state' => $i->workflowState ? [
                    'name' => $i->workflowState->name,
                    'type' => $i->workflowState->type,
                    'color' => $i->workflowState->color,
                ] : null,
                'assignee' => $i->assignee ? [
                    'id' => $i->assignee->id,
                    'name' => $i->assignee->name,
                    'email' => $i->assignee->email,
                ] : null,
                'project' => $i->project ? [
                    'id' => $i->project->id,
                    'name' => $i->project->name,
                    'slug' => $i->project->slug,
                    'color' => $i->project->color,
                    'icon' => $i->project->icon,
                ] : null,
                'labels' => $i->labels->map(fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'updated_at' => $i->updated_at?->toIso8601String(),
            ])->all(),
            'priorities' => $this->priorityOptions(),
            'filters' => [
                'team' => $team->key,
                'assignee' => $assigneeFilter,
                'state' => $stateFilter,
            ],
        ]);
    }

    private function emptyResponse(?string $assigneeFilter, ?string $stateFilter, ?string $teamKey = null): Response
    {
        return Inertia::render('issues/Index', [
            'issues' => [],
            'states' => [],
            'team' => null,
            'priorities' => $this->priorityOptions(),
            'filters' => [
                'team' => $teamKey,
                'assignee' => $assigneeFilter,
                'state' => $stateFilter,
            ],
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function priorityOptions(): array
    {
        $out = [];
        foreach (IssuePriority::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
