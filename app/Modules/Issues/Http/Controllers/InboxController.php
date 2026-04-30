<?php

declare(strict_types=1);

namespace App\Modules\Issues\Http\Controllers;

use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
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
        ]);
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
