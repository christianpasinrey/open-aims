<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Derived "inbox" feed.
 *
 * The data model has no notifications table yet, so the feed is computed
 * from existing rows: issues where the user is creator/assignee, recent
 * comments on those issues, and projects where they are the lead.
 *
 * Each entry has a `kind` (assigned | created | commented | project_update)
 * and an `occurred_at` timestamp the frontend uses for the timeline.
 */
final class InboxController
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()?->getKey();
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;

        if ($userId === 0 || ! $workspace instanceof Workspace) {
            return Inertia::render('inbox/Index', [
                'feed' => [],
                'counts' => ['total' => 0, 'assigned' => 0, 'comments' => 0],
            ]);
        }

        $entries = collect();

        // 1. Issues assigned to me — show latest activity.
        $assigned = Issue::query()
            ->where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $userId)
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color',
                'creator:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        foreach ($assigned as $issue) {
            $entries->push([
                'kind' => 'assigned',
                'occurred_at' => $issue->updated_at?->toIso8601String(),
                'issue' => $this->issuePayload($issue),
                'actor' => $issue->creator ? [
                    'id' => $issue->creator->id,
                    'name' => $issue->creator->name,
                    'email' => $issue->creator->email,
                ] : null,
                'snippet' => null,
            ]);
        }

        // 2. Issues I created (excluding ones I'm also assignee on, already covered)
        $created = Issue::query()
            ->where('workspace_id', $workspace->id)
            ->where('creator_user_id', $userId)
            ->where(function ($q) use ($userId) {
                $q->whereNull('assignee_user_id')->orWhere('assignee_user_id', '!=', $userId);
            })
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color',
                'assignee:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        foreach ($created as $issue) {
            $entries->push([
                'kind' => 'created',
                'occurred_at' => $issue->updated_at?->toIso8601String(),
                'issue' => $this->issuePayload($issue),
                'actor' => $issue->assignee ? [
                    'id' => $issue->assignee->id,
                    'name' => $issue->assignee->name,
                    'email' => $issue->assignee->email,
                ] : null,
                'snippet' => null,
            ]);
        }

        // 3. Recent comments on issues I'm involved with (assignee or creator)
        $comments = Comment::query()
            ->whereHas('issue', function ($q) use ($userId, $workspace) {
                $q->where('workspace_id', $workspace->id)
                    ->where(function ($qq) use ($userId) {
                        $qq->where('assignee_user_id', $userId)
                            ->orWhere('creator_user_id', $userId);
                    });
            })
            ->where('user_id', '!=', $userId)
            ->with([
                'user:id,name,email',
                'issue.team:id,key,name,color',
                'issue.workflowState:id,name,type,color',
            ])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        foreach ($comments as $comment) {
            if ($comment->issue === null) {
                continue;
            }
            $entries->push([
                'kind' => 'commented',
                'occurred_at' => $comment->created_at?->toIso8601String(),
                'issue' => $this->issuePayload($comment->issue),
                'actor' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'email' => $comment->user->email,
                ] : null,
                'snippet' => $this->snippet((string) $comment->body),
            ]);
        }

        // Sort by occurrence and dedupe identical issue+kind in same minute.
        $sorted = $entries
            ->filter(static fn (array $e) => $e['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->values()
            ->take(60)
            ->all();

        return Inertia::render('inbox/Index', [
            'feed' => $sorted,
            'counts' => [
                'total' => count($sorted),
                'assigned' => $assigned->count(),
                'comments' => $comments->count(),
            ],
            'preview' => $this->buildPreview(
                $request->query('preview'),
                $workspace,
            ),
        ]);
    }

    /**
     * Resolve the issue picked by `?preview=LAM-275` and return a
     * compact payload the inbox right rail can render without leaving
     * /inbox. Returns null when no preview is requested or the issue
     * cannot be reached from this workspace.
     */
    private function buildPreview(mixed $identifier, Workspace $workspace): ?array
    {
        if (! is_string($identifier) || $identifier === '') {
            return null;
        }
        if (preg_match('/^([A-Za-z]+)-(\d+)$/', $identifier, $m) !== 1) {
            return null;
        }
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($m[1]))
            ->first();
        if ($team === null) {
            return null;
        }
        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', (int) $m[2])
            ->with([
                'workflowState:id,name,type,color,position',
                'assignee:id,name,email',
                'creator:id,name,email',
                'project:id,name,slug,color,icon',
                'labels:id,name,color',
            ])
            ->first();
        if ($issue === null) {
            return null;
        }

        $description = $issue->description;
        if ($description !== null && mb_strlen($description) > 8000) {
            $description = mb_substr($description, 0, 8000)."\n\n…(truncated)";
        }

        $comments = Comment::query()
            ->where('issue_id', $issue->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Comment $c): array => [
                'id' => $c->id,
                'body' => $c->body,
                'user' => $c->user ? [
                    'name' => $c->user->name,
                    'email' => $c->user->email,
                ] : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'identifier' => $team->key.'-'.$issue->number,
            'title' => $issue->title,
            'description' => $description,
            'priority' => (int) ($issue->priority?->value ?? 0),
            'state' => $issue->workflowState ? [
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
                'name' => $issue->project->name,
                'slug' => $issue->project->slug,
                'color' => $issue->project->color,
                'icon' => $issue->project->icon,
            ] : null,
            'labels' => $issue->labels->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->all(),
            'comments' => $comments,
            'team' => [
                'key' => $team->key,
                'name' => $team->name,
                'color' => $team->color,
            ],
            'updated_at' => $issue->updated_at?->toIso8601String(),
            'created_at' => $issue->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function issuePayload(Issue $issue): array
    {
        return [
            'id' => $issue->id,
            'identifier' => ($issue->team?->key ?? '?').'-'.$issue->number,
            'title' => $issue->title,
            'priority' => (int) ($issue->priority?->value ?? 0),
            'state' => $issue->workflowState ? [
                'name' => $issue->workflowState->name,
                'type' => $issue->workflowState->type,
                'color' => $issue->workflowState->color,
            ] : null,
            'team' => $issue->team ? [
                'key' => $issue->team->key,
                'name' => $issue->team->name,
                'color' => $issue->team->color,
            ] : null,
        ];
    }

    private function snippet(string $body): string
    {
        $stripped = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return mb_strlen($stripped) > 160
            ? rtrim(mb_substr($stripped, 0, 160)).'…'
            : $stripped;
    }
}
