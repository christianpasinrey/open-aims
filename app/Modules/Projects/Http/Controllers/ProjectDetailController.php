<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Models\User;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectDetailController
{
    public function show(Request $request, string $slug): Response
    {
        $tab = $request->query('tab');
        $tab = is_string($tab) && in_array($tab, ['overview', 'activity', 'issues'], true)
            ? $tab
            : 'overview';
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $slug)
            ->with([
                'lead:id,name,email',
                'members.user:id,name,email',
                'milestones',
                'teams:id,name,key,color',
                'labels:id,team_id,name,color',
                'resources.media',
                'resources.creator:id,name,email',
            ])
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        $issues = Issue::query()
            ->where('project_id', $project->id)
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

        $statePositions = WorkflowState::query()
            ->whereIn('team_id', $project->teams->pluck('id'))
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position'])
            ->groupBy('name')
            ->map(static fn ($group) => $group->first());

        $totalIssues = $issues->count();
        $completedIssues = $issues
            ->filter(static fn (Issue $i) => $i->workflowState?->type === 'completed')
            ->count();
        $startedIssues = $issues
            ->filter(static fn (Issue $i) => $i->workflowState?->type === 'started')
            ->count();
        $progress = $totalIssues > 0 ? (int) round(($completedIssues / $totalIssues) * 100) : 0;

        // Build per-assignee aggregates for the right-rail Progress > Assignees tab.
        $assigneeBuckets = [];
        foreach ($issues as $issue) {
            /** @var Issue $issue */
            $key = $issue->assignee?->id ?? 0;
            if (! isset($assigneeBuckets[$key])) {
                $assigneeBuckets[$key] = [
                    'user' => $issue->assignee ? [
                        'id' => $issue->assignee->id,
                        'name' => $issue->assignee->name,
                        'email' => $issue->assignee->email,
                    ] : null,
                    'total' => 0,
                    'completed' => 0,
                ];
            }
            $assigneeBuckets[$key]['total']++;
            if ($issue->workflowState?->type === 'completed') {
                $assigneeBuckets[$key]['completed']++;
            }
        }
        $assignees = array_map(static function (array $row): array {
            $row['percent'] = $row['total'] > 0
                ? (int) round(($row['completed'] / $row['total']) * 100)
                : 0;

            return $row;
        }, array_values($assigneeBuckets));
        usort($assignees, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        // Attached labels — shown as chips in the right rail.
        $attachedLabels = $project->labels->map(fn (Label $l): array => [
            'id' => $l->id,
            'name' => $l->name,
            'color' => $l->color,
        ])->values()->all();

        // Pool for the label picker: every label belonging to one of the
        // project's teams that is not already attached.
        $teamIds = $project->teams->pluck('id');
        $attachedLabelIds = $project->labels->pluck('id')->all();
        $availableLabels = Label::query()
            ->whereIn('team_id', $teamIds)
            ->whereNotIn('id', $attachedLabelIds)
            ->orderBy('name')
            ->get(['id', 'team_id', 'name', 'color'])
            ->map(static fn (Label $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])
            ->values()
            ->all();

        // Pool for the member picker: workspace members who are not already
        // attached as ProjectMember.
        $existingMemberIds = $project->members->pluck('user_id')->all();
        $eligibleMemberIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('user_id', $existingMemberIds)
            ->pluck('user_id')
            ->all();
        $availableMembers = User::query()
            ->whereIn('id', $eligibleMemberIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->all();

        return Inertia::render('projects/Show', [
            'tab' => $tab,
            'progress' => [
                'total' => $totalIssues,
                'completed' => $completedIssues,
                'started' => $startedIssues,
                'percent' => $progress,
            ],
            'assignees' => $assignees,
            'labels' => $attachedLabels,
            'available_labels' => $availableLabels,
            'available_members' => $availableMembers,
            'states' => $statePositions->values()->map(static fn ($s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
                'position' => $s->position,
            ])->all(),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'state' => $project->state?->value,
                'priority' => (int) ($project->priority ?? 0),
                'color' => $project->color,
                'icon' => $project->icon,
                'start_date' => $project->start_date?->toDateString(),
                'target_date' => $project->target_date?->toDateString(),
                'completed_at' => $project->completed_at?->toIso8601String(),
                'lead' => $project->lead ? [
                    'id' => $project->lead->id,
                    'name' => $project->lead->name,
                    'email' => $project->lead->email,
                ] : null,
                'members' => $project->members->map(fn ($m): array => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'role' => $m->role,
                ])->filter(static fn ($m) => $m['id'] !== null)->values()->all(),
                'milestones' => $project->milestones->map(fn ($ms): array => [
                    'id' => $ms->id,
                    'name' => $ms->name,
                    'description' => $ms->description,
                    'target_date' => $ms->target_date,
                    // TODO: when an `issues.project_milestone_id` column exists,
                    // count issues per milestone here and compute the % completed.
                    'issue_count' => 0,
                    'percent' => 0,
                ])->all(),
                'teams' => $project->teams->map(fn ($t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'key' => $t->key,
                    'color' => $t->color,
                ])->all(),
                'resources' => $project->resources->map(function ($r): array {
                    $media = $r->getFirstMedia('attachment');

                    return [
                        'id' => $r->id,
                        'type' => $r->type,
                        'name' => $r->name,
                        'url' => $r->type === 'link'
                            ? $r->url
                            : ($media?->getFullUrl() ?? null),
                        'mime_type' => $media?->mime_type,
                        'size' => $media ? (int) $media->size : null,
                        'created_at' => $r->created_at?->toIso8601String(),
                        'creator' => $r->creator ? [
                            'id' => $r->creator->id,
                            'name' => $r->creator->name,
                            'email' => $r->creator->email,
                        ] : null,
                    ];
                })->all(),
            ],
            'issues' => $issues->map(fn (Issue $i): array => [
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
                'labels' => $i->labels->map(fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'updated_at' => $i->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
