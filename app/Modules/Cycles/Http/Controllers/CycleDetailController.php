<?php

declare(strict_types=1);

namespace App\Modules\Cycles\Http\Controllers;

use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CycleDetailController
{
    public function show(Request $request, int $number): Response
    {
        $teamKey = $request->query('team');
        $teamKey = is_string($teamKey) && $teamKey !== '' ? strtoupper($teamKey) : null;
        if ($teamKey === null) {
            throw new NotFoundHttpException('Team is required.');
        }

        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $teamKey)
            ->first();

        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $cycle = Cycle::query()
            ->where('team_id', $team->id)
            ->where('number', $number)
            ->first();

        if ($cycle === null) {
            throw new NotFoundHttpException('Cycle not found.');
        }

        $issues = Issue::query()
            ->where('cycle_id', $cycle->id)
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color,position',
                'assignee:id,name,email',
                'labels:id,name,color',
                'project:id,name,slug,color,icon',
            ])
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $states = WorkflowState::query()
            ->where('team_id', $team->id)
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position']);

        $totalIssues = $issues->count();
        $completedIssues = $issues
            ->filter(static fn (Issue $i) => $i->workflowState?->type === 'completed')
            ->count();
        $startedIssues = $issues
            ->filter(static fn (Issue $i) => $i->workflowState?->type === 'started')
            ->count();
        $percent = $totalIssues > 0 ? (int) round(($completedIssues / $totalIssues) * 100) : 0;

        // Per-assignee breakdown
        $assigneeGroups = $issues->groupBy(static fn (Issue $i) => $i->assignee_user_id);
        $assignees = $assigneeGroups
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection<int,Issue> $group */
                $first = $group->first();
                $user = $first?->assignee;
                $total = $group->count();
                $completed = $group
                    ->filter(static fn (Issue $i) => $i->workflowState?->type === 'completed')
                    ->count();
                $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
                    'user' => $user !== null ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ] : null,
                    'completed' => $completed,
                    'total' => $total,
                    'percent' => $percent,
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        // Cycle status
        $now = CarbonImmutable::now();
        $startsAt = $cycle->starts_at;
        $endsAt = $cycle->ends_at;
        $status = 'past';
        if ($cycle->completed_at !== null) {
            $status = 'completed';
        } elseif ($startsAt !== null && $endsAt !== null && $startsAt->lte($now) && $endsAt->gte($now)) {
            $status = 'current';
        } elseif ($startsAt !== null && $startsAt->gt($now)) {
            $status = 'upcoming';
        }

        $weekdaysLeft = null;
        if ($status === 'current' && $endsAt !== null) {
            $weekdaysLeft = $this->countWeekdays($now->startOfDay(), CarbonImmutable::instance($endsAt)->startOfDay());
        }

        return Inertia::render('cycles/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'cycle' => [
                'id' => $cycle->id,
                'number' => $cycle->number,
                'name' => $cycle->name,
                'description' => $cycle->description,
                'starts_at' => $cycle->starts_at?->toDateString(),
                'ends_at' => $cycle->ends_at?->toDateString(),
                'completed_at' => $cycle->completed_at?->toIso8601String(),
                'status' => $status,
                'weekdays_left' => $weekdaysLeft,
            ],
            'progress' => [
                'total' => $totalIssues,
                'completed' => $completedIssues,
                'started' => $startedIssues,
                'percent' => $percent,
                'scope_change_percent' => null,
            ],
            'assignees' => $assignees,
            'states' => $states->map(fn (WorkflowState $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
                'position' => $s->position,
            ])->all(),
            'issues' => $issues->map(fn (Issue $i): array => [
                'id' => $i->id,
                'identifier' => ($i->team?->key ?? $team->key).'-'.$i->number,
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
        ]);
    }

    private function countWeekdays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($from->gt($to)) {
            return 0;
        }
        $count = 0;
        $cursor = $from;
        while ($cursor->lte($to)) {
            $dow = (int) $cursor->dayOfWeek;
            if ($dow !== 0 && $dow !== 6) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
