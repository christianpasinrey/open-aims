<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

            return Issue::create([
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

        $issue->fill($data)->save();

        if (is_array($labels)) {
            $issue->labels()->sync($labels);
        }

        return back();
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
