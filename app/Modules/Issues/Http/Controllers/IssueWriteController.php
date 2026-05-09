<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Models\User;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueActivity;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-type ActivityPayload array<string,mixed>|null
 */

/**
 * Mutations on issues from the repo-style UI: create + partial update.
 *
 * No granular permissions yet — workspace membership is enforced upstream
 * by ResolveWorkspace + the auth middleware. We accept whichever fields
 * the client sends and ignore the rest.
 */
final class IssueWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspace();
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $data = $request->validate([
            'title' => 'required|string|max:500',
            'team_key' => 'required|string|max:16',
            'state_id' => 'nullable|integer|exists:workflow_states,id',
            'priority' => 'nullable|integer|min:0|max:4',
            'description' => 'nullable|string',
            'project_id' => 'nullable|integer|exists:projects,id',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $stateId = $data['state_id'] ?? null;
        if ($stateId === null) {
            $stateId = (int) WorkflowState::query()
                ->where('team_id', $team->id)
                ->orderBy('position')
                ->value('id');
        }

        $issue = DB::transaction(function () use ($team, $workspace, $user, $stateId, $data): Issue {
            $team->refresh();
            $next = ((int) $team->issue_counter) + 1;
            $team->update(['issue_counter' => $next]);

            $issue = Issue::create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'project_id' => $data['project_id'] ?? null,
                'cycle_id' => $data['cycle_id'] ?? null,
                'number' => $next,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'workflow_state_id' => $stateId,
                'priority' => $data['priority'] ?? 0,
                'assignee_user_id' => $data['assignee_user_id'] ?? null,
                'creator_user_id' => $user->getKey(),
            ]);

            IssueActivity::create([
                'issue_id' => $issue->id,
                'actor_user_id' => $user->getKey(),
                'kind' => 'created',
                'payload' => null,
                'occurred_at' => now(),
            ]);

            return $issue;
        });

        return redirect()->route('issues.show', [
            'identifier' => $team->key.'-'.$issue->number,
        ]);
    }

    public function update(Request $request, string $identifier): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:500',
            'description' => 'sometimes|nullable|string',
            'workflow_state_id' => 'sometimes|integer|exists:workflow_states,id',
            'priority' => 'sometimes|integer|min:0|max:4',
            'assignee_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'project_id' => 'sometimes|nullable|integer|exists:projects,id',
            'cycle_id' => 'sometimes|nullable|integer|exists:cycles,id',
            'estimate' => 'sometimes|nullable|numeric|min:0',
            'due_date' => 'sometimes|nullable|date',
            'labels' => 'sometimes|array',
            'labels.*' => 'integer|exists:labels,id',
        ]);

        $labels = $data['labels'] ?? null;
        unset($data['labels']);

        // Capture before-state for diffing, before any mutation runs.
        $before = [
            'title' => $issue->title,
            'description' => $issue->description,
            'workflow_state_id' => $issue->workflow_state_id,
            'priority' => (int) ($issue->priority?->value ?? 0),
            'assignee_user_id' => $issue->assignee_user_id,
            'project_id' => $issue->project_id,
            'cycle_id' => $issue->cycle_id,
            'estimate' => $issue->estimate,
            'due_date' => $issue->due_date?->toDateString(),
        ];
        $beforeLabelIds = $issue->labels()->pluck('labels.id')->all();

        if (array_key_exists('workflow_state_id', $data)) {
            $newState = WorkflowState::query()->find($data['workflow_state_id']);
            if ($newState === null || (int) $newState->team_id !== (int) $issue->team_id) {
                abort(422, 'Invalid state for this team.');
            }
            $type = $newState->type;
            if ($type === 'started' && $issue->started_at === null) {
                $data['started_at'] = now();
            }
            if ($type === 'completed') {
                $data['completed_at'] = now();
            } elseif ($type === 'canceled') {
                $data['canceled_at'] = now();
            }
        }

        DB::transaction(function () use ($issue, $data, $labels, $before, $beforeLabelIds, $request): void {
            $issue->fill($data)->save();

            if (is_array($labels)) {
                $issue->labels()->sync($labels);
            }

            $this->recordIssueChanges(
                $issue->fresh(['labels']),
                $before,
                $beforeLabelIds,
                $request->user()?->getKey(),
            );
        });

        return back();
    }

    public function archive(Request $request, string $identifier): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);
        $issue->forceFill(['archived_at' => now()])->save();

        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $request->user()?->getKey(),
            'kind' => 'archived',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        return redirect()->route('issues.index', [
            'team' => $issue->team()->value('key'),
        ]);
    }

    public function unarchive(Request $request, string $identifier): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);
        $issue->forceFill(['archived_at' => null])->save();

        IssueActivity::create([
            'issue_id' => $issue->id,
            'actor_user_id' => $request->user()?->getKey(),
            'kind' => 'unarchived',
            'payload' => null,
            'occurred_at' => now(),
        ]);

        return back();
    }

    public function destroy(Request $request, string $identifier): RedirectResponse
    {
        $issue = $this->resolveIssue($identifier);
        $teamKey = $issue->team()->value('key');
        $issue->delete();

        return redirect()->route('issues.index', ['team' => $teamKey]);
    }

    /**
     * Diff before/after of an issue update and emit one IssueActivity row
     * per significant change. Payload shapes match what
     * resources/js/components/repo/issues/IssueActivityRow.vue already
     * knows how to render — keep them in sync if you add new kinds.
     *
     * @param  array<string,mixed>  $before
     * @param  list<int>  $beforeLabelIds
     */
    private function recordIssueChanges(
        Issue $issue,
        array $before,
        array $beforeLabelIds,
        ?int $actorId,
    ): void {
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

    private function resolveIssue(string $identifier): Issue
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $m) !== 1) {
            throw new NotFoundHttpException('Invalid issue identifier.');
        }

        $workspace = $this->workspace();
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($m[1]))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', (int) $m[2])
            ->first();
        if ($issue === null) {
            throw new NotFoundHttpException('Issue not found.');
        }

        return $issue;
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
