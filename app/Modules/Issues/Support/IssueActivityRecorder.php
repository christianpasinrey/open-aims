<?php

declare(strict_types=1);

namespace App\Modules\Issues\Support;

use App\Models\User;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\WorkflowState;

/**
 * Single source of truth for issue activity logging, shared by the UI
 * controllers and the MCP tools so every write path produces the same
 * IssueActivity rows (and therefore the same notifications / Telegram feed).
 */
final class IssueActivityRecorder
{
    public function created(Issue $issue, ?int $actorId): void
    {
        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $actorId,
            'kind' => 'created',
            'payload' => null,
            'occurred_at' => now(),
        ]);
    }

    public function archived(Issue $issue, ?int $actorId, bool $archived): void
    {
        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $actorId,
            'kind' => $archived ? 'archived' : 'unarchived',
            'payload' => null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Snapshot the diff-relevant fields of an issue before a mutation.
     *
     * @return array{before:array<string,mixed>,labelIds:list<int>}
     */
    public function snapshot(Issue $issue): array
    {
        return [
            'before' => [
                'title' => $issue->title,
                'description' => $issue->description,
                'workflow_state_id' => $issue->workflow_state_id,
                'priority' => (int) ($issue->priority?->value ?? 0),
                'assignee_user_id' => $issue->assignee_user_id,
                'project_id' => $issue->project_id,
                'cycle_id' => $issue->cycle_id,
                'estimate' => $issue->estimate,
                'due_date' => $issue->due_date?->toDateString(),
            ],
            'labelIds' => $issue->labels()->pluck('labels.id')->all(),
        ];
    }

    /**
     * Diff before/after of an issue and emit one IssueActivity row per change.
     *
     * @param  array<string,mixed>  $before
     * @param  list<int>  $beforeLabelIds
     */
    public function record(Issue $issue, array $before, array $beforeLabelIds, ?int $actorId): void
    {
        $now = now();
        $base = [
            'issue_id' => $issue->id,
            'actor_user_id' => $actorId,
            'occurred_at' => $now,
        ];

        if ($before['title'] !== $issue->title) {
            IssueActivity::create($base + [
                'kind' => 'title_changed',
                'payload' => ['from' => $before['title'], 'to' => $issue->title],
            ]);
        }

        if (($before['description'] ?? null) !== $issue->description) {
            IssueActivity::create($base + [
                'kind' => 'description_changed',
                'payload' => null,
            ]);
        }

        if ((int) $before['workflow_state_id'] !== (int) $issue->workflow_state_id) {
            $fromState = WorkflowState::query()->find($before['workflow_state_id']);
            $toState = $issue->workflowState ?? WorkflowState::query()->find($issue->workflow_state_id);
            IssueActivity::create($base + [
                'kind' => 'status_changed',
                'payload' => [
                    'from' => $fromState ? [
                        'id' => $fromState->id,
                        'name' => $fromState->name,
                        'type' => $fromState->type,
                        'color' => $fromState->color,
                    ] : null,
                    'to' => $toState ? [
                        'id' => $toState->id,
                        'name' => $toState->name,
                        'type' => $toState->type,
                        'color' => $toState->color,
                    ] : null,
                ],
            ]);
        }

        $newPriority = (int) ($issue->priority?->value ?? 0);
        if ((int) $before['priority'] !== $newPriority) {
            $labels = [
                0 => 'No priority',
                1 => 'Urgent',
                2 => 'High',
                3 => 'Medium',
                4 => 'Low',
            ];
            IssueActivity::create($base + [
                'kind' => 'priority_changed',
                'payload' => [
                    'from' => (int) $before['priority'],
                    'from_label' => $labels[(int) $before['priority']] ?? '—',
                    'to' => $newPriority,
                    'to_label' => $labels[$newPriority] ?? '—',
                ],
            ]);
        }

        $beforeAssignee = $before['assignee_user_id'];
        $afterAssignee = $issue->assignee_user_id;
        if ($beforeAssignee !== $afterAssignee) {
            if ($afterAssignee === null) {
                IssueActivity::create($base + [
                    'kind' => 'unassigned',
                    'payload' => null,
                ]);
            } else {
                $user = User::query()->find($afterAssignee);
                IssueActivity::create($base + [
                    'kind' => 'assigned',
                    'payload' => [
                        'user_id' => $afterAssignee,
                        'user_name' => $user?->name,
                    ],
                ]);
            }
        }

        if ($before['project_id'] !== $issue->project_id) {
            if ($issue->project_id === null) {
                IssueActivity::create($base + [
                    'kind' => 'project_unset',
                    'payload' => null,
                ]);
            } else {
                $project = Project::query()->find($issue->project_id);
                IssueActivity::create($base + [
                    'kind' => 'project_set',
                    'payload' => [
                        'project_id' => $issue->project_id,
                        'project_name' => $project?->name,
                        'project_slug' => $project?->slug,
                    ],
                ]);
            }
        }

        if ($before['cycle_id'] !== $issue->cycle_id) {
            if ($issue->cycle_id === null) {
                IssueActivity::create($base + [
                    'kind' => 'cycle_unset',
                    'payload' => null,
                ]);
            } else {
                $cycle = Cycle::query()->find($issue->cycle_id);
                IssueActivity::create($base + [
                    'kind' => 'cycle_set',
                    'payload' => [
                        'cycle_id' => $issue->cycle_id,
                        'cycle_name' => $cycle?->name,
                        'cycle_number' => $cycle?->number,
                    ],
                ]);
            }
        }

        $beforeDue = $before['due_date'];
        $afterDue = $issue->due_date?->toDateString();
        if ($beforeDue !== $afterDue) {
            IssueActivity::create($base + [
                'kind' => 'due_date_changed',
                'payload' => ['from' => $beforeDue, 'to' => $afterDue],
            ]);
        }

        if ((float) ($before['estimate'] ?? 0) !== (float) ($issue->estimate ?? 0)) {
            IssueActivity::create($base + [
                'kind' => 'estimate_changed',
                'payload' => [
                    'from' => $before['estimate'],
                    'to' => $issue->estimate,
                ],
            ]);
        }

        $afterLabelIds = $issue->labels->pluck('id')->all();
        $added = array_values(array_diff($afterLabelIds, $beforeLabelIds));
        $removed = array_values(array_diff($beforeLabelIds, $afterLabelIds));
        if ($added !== [] || $removed !== []) {
            $allIds = array_unique(array_merge($added, $removed));
            $labelsById = Label::query()
                ->whereIn('id', $allIds)
                ->get(['id', 'name', 'color'])
                ->keyBy('id');

            foreach ($added as $id) {
                $l = $labelsById->get($id);
                if ($l === null) {
                    continue;
                }
                IssueActivity::create($base + [
                    'kind' => 'label_added',
                    'payload' => [
                        'label_id' => $id,
                        'label_name' => $l->name,
                        'label_color' => $l->color,
                    ],
                ]);
            }
            foreach ($removed as $id) {
                $l = $labelsById->get($id);
                if ($l === null) {
                    continue;
                }
                IssueActivity::create($base + [
                    'kind' => 'label_removed',
                    'payload' => [
                        'label_id' => $id,
                        'label_name' => $l->name,
                        'label_color' => $l->color,
                    ],
                ]);
            }
        }
    }
}
