<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectMilestoneDetailController
{
    public function show(string $slug, int $milestone): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->with([
                'teams:id,name,key,color',
                'milestones' => fn ($q) => $q->withCount([
                    'issues as total_issues' => fn ($i) => $i->whereNull('archived_at'),
                    'issues as completed_issues' => fn ($i) => $i
                        ->whereNull('archived_at')
                        ->whereHas('workflowState', fn ($w) => $w->where('type', 'completed')),
                ]),
            ])
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        /** @var ProjectMilestone|null $current */
        $current = $project->milestones->firstWhere('id', $milestone);
        if ($current === null) {
            throw new NotFoundHttpException('Milestone not found.');
        }

        $issues = Issue::query()
            ->where('project_id', $project->id)
            ->where('project_milestone_id', $current->id)
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color,position',
                'assignee:id,name,email',
                'labels:id,name,color',
            ])
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        // States of the project's teams plus any team an issue lives in, so a
        // group always has a known position and colour.
        $teamIds = $project->teams->pluck('id')
            ->merge($issues->pluck('team_id'))
            ->unique()
            ->values();
        $states = WorkflowState::query()
            ->whereIn('team_id', $teamIds)
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position'])
            ->groupBy('name')
            ->map(static fn ($group) => $group->first())
            ->values();

        $total = $issues->count();
        $completed = $issues->filter(static fn (Issue $i) => $i->workflowState?->type === 'completed')->count();
        $started = $issues->filter(static fn (Issue $i) => $i->workflowState?->type === 'started')->count();

        return Inertia::render('projects/milestones/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'color' => $project->color,
                'icon' => $project->icon,
                'teams' => $project->teams->map(static fn ($t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'key' => $t->key,
                    'color' => $t->color,
                ])->all(),
            ],
            'milestone' => [
                'id' => $current->id,
                'name' => $current->name,
                'description' => $current->description,
                'target_date' => $current->target_date?->toDateString(),
                'completed_at' => $current->completed_at?->toIso8601String(),
                ...$this->schedule($current, $total, $completed),
            ],
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'started' => $started,
                'percent' => $this->percent($completed, $total),
            ],
            'state_breakdown' => $issues
                ->groupBy(static fn (Issue $i) => $i->workflowState?->name ?? '—')
                ->map(static fn ($group, string $name): array => [
                    'name' => $name,
                    'type' => $group->first()->workflowState?->type ?? 'unstarted',
                    'color' => $group->first()->workflowState?->color,
                    'position' => (int) ($group->first()->workflowState?->position ?? PHP_INT_MAX),
                    'count' => $group->count(),
                ])
                ->sortBy('position')
                ->values()
                ->all(),
            'assignees' => $issues
                ->groupBy(static fn (Issue $i) => $i->assignee_user_id ?? 0)
                ->map(fn ($group): array => [
                    'user' => $group->first()->assignee ? [
                        'id' => $group->first()->assignee->id,
                        'name' => $group->first()->assignee->name,
                        'email' => $group->first()->assignee->email,
                    ] : null,
                    'total' => $group->count(),
                    'completed' => $done = $group
                        ->filter(static fn (Issue $i) => $i->workflowState?->type === 'completed')
                        ->count(),
                    'percent' => $this->percent($done, $group->count()),
                ])
                ->sortByDesc('total')
                ->values()
                ->all(),
            'states' => $states->map(static fn (WorkflowState $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
                'position' => $s->position,
            ])->all(),
            'milestones' => $project->milestones->map(fn (ProjectMilestone $ms): array => [
                'id' => $ms->id,
                'name' => $ms->name,
                'target_date' => $ms->target_date?->toDateString(),
                'completed_at' => $ms->completed_at?->toIso8601String(),
                'percent' => $this->percent((int) $ms->completed_issues, (int) $ms->total_issues),
            ])->all(),
            'issues' => $issues->map(static fn (Issue $i): array => [
                'id' => $i->id,
                'identifier' => ($i->team?->key ?? '?').'-'.$i->number,
                'title' => $i->title,
                'priority' => (int) ($i->priority?->value ?? 0),
                'state_name' => $i->workflowState?->name,
                'state' => $i->workflowState ? [
                    'name' => $i->workflowState->name,
                    'type' => $i->workflowState->type,
                    'color' => $i->workflowState->color,
                ] : null,
                'assignee' => $i->assignee ? [
                    'id' => $i->assignee->id,
                    'name' => $i->assignee->name,
                ] : null,
                'labels' => $i->labels->map(static fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'updated_at' => $i->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * @return array{status: 'completed'|'done'|'overdue'|'on_track'|'unscheduled', days_left: ?int}
     */
    private function schedule(ProjectMilestone $milestone, int $total, int $completed): array
    {
        if ($milestone->completed_at !== null) {
            return ['status' => 'completed', 'days_left' => null];
        }

        $daysLeft = $milestone->target_date === null
            ? null
            : (int) CarbonImmutable::today()->diffInDays(
                CarbonImmutable::instance($milestone->target_date)->startOfDay(),
                false,
            );

        // Every issue finished but nobody closed the milestone: that is a
        // prompt to complete it, not a missed deadline.
        if ($total > 0 && $completed === $total) {
            return ['status' => 'done', 'days_left' => $daysLeft];
        }

        if ($daysLeft === null) {
            return ['status' => 'unscheduled', 'days_left' => null];
        }

        return [
            'status' => $daysLeft < 0 ? 'overdue' : 'on_track',
            'days_left' => $daysLeft,
        ];
    }

    private function percent(int $done, int $total): int
    {
        return $total > 0 ? (int) round(($done / $total) * 100) : 0;
    }
}
