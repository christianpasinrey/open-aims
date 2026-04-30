<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Enums\IssuePriority;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IssueDetailController
{
    public function show(string $identifier): Response
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $m) !== 1) {
            throw new NotFoundHttpException('Invalid issue identifier.');
        }

        $teamKey = strtoupper($m[1]);
        $number = (int) $m[2];

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

        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', $number)
            ->with([
                'assignee:id,name,email',
                'creator:id,name,email',
                'workflowState:id,name,type,color,position',
                'labels:id,name,color',
                'project:id,name,slug,color,icon',
                'cycle:id,number,name,starts_at,ends_at',
                'parent:id,team_id,number,title',
                'children:id,team_id,number,title,workflow_state_id,priority,assignee_user_id',
                'children.workflowState:id,name,type,color',
                'children.assignee:id,name',
            ])
            ->first();

        if ($issue === null) {
            throw new NotFoundHttpException('Issue not found.');
        }

        $comments = Comment::query()
            ->where('issue_id', $issue->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        $states = WorkflowState::query()
            ->where('team_id', $team->id)
            ->orderBy('position')
            ->get(['id', 'name', 'type', 'color', 'position']);

        return Inertia::render('issues/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'issue' => [
                'id' => $issue->id,
                'identifier' => $team->key.'-'.$issue->number,
                'number' => $issue->number,
                'title' => $issue->title,
                'description' => $issue->description,
                'priority' => (int) ($issue->priority?->value ?? 0),
                'priority_label' => ($issue->priority ?? IssuePriority::None)->label(),
                'estimate' => $issue->estimate,
                'due_date' => $issue->due_date?->toDateString(),
                'started_at' => $issue->started_at?->toIso8601String(),
                'completed_at' => $issue->completed_at?->toIso8601String(),
                'canceled_at' => $issue->canceled_at?->toIso8601String(),
                'state' => $issue->workflowState ? [
                    'id' => $issue->workflowState->id,
                    'name' => $issue->workflowState->name,
                    'type' => $issue->workflowState->type,
                    'color' => $issue->workflowState->color,
                ] : null,
                'assignee' => $issue->assignee ? [
                    'id' => $issue->assignee->id,
                    'name' => $issue->assignee->name,
                    'email' => $issue->assignee->email,
                ] : null,
                'creator' => $issue->creator ? [
                    'id' => $issue->creator->id,
                    'name' => $issue->creator->name,
                    'email' => $issue->creator->email,
                ] : null,
                'project' => $issue->project ? [
                    'id' => $issue->project->id,
                    'name' => $issue->project->name,
                    'slug' => $issue->project->slug,
                    'color' => $issue->project->color,
                ] : null,
                'labels' => $issue->labels->map(fn ($l): array => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'color' => $l->color,
                ])->all(),
                'parent' => $issue->parent ? [
                    'identifier' => $team->key.'-'.$issue->parent->number,
                    'title' => $issue->parent->title,
                ] : null,
                'children' => $issue->children->map(fn (Issue $c): array => [
                    'id' => $c->id,
                    'identifier' => $team->key.'-'.$c->number,
                    'title' => $c->title,
                    'priority' => (int) ($c->priority?->value ?? 0),
                    'state' => $c->workflowState ? [
                        'name' => $c->workflowState->name,
                        'type' => $c->workflowState->type,
                        'color' => $c->workflowState->color,
                    ] : null,
                    'assignee' => $c->assignee ? [
                        'id' => $c->assignee->id,
                        'name' => $c->assignee->name,
                    ] : null,
                ])->all(),
                'created_at' => $issue->created_at?->toIso8601String(),
                'updated_at' => $issue->updated_at?->toIso8601String(),
            ],
            'comments' => $comments->map(fn (Comment $c): array => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'email' => $c->user->email,
                ] : null,
                'created_at' => $c->created_at?->toIso8601String(),
                'edited_at' => $c->edited_at?->toIso8601String(),
            ])->all(),
            'states' => $states->map(fn (WorkflowState $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'color' => $s->color,
            ])->all(),
        ]);
    }
}
