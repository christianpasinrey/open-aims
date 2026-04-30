<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Personal inbox for the authenticated user: latest assignments, '
    .'updates on issues they created, and comments by others on issues '
    .'they\'re involved with.'
)]
class InboxList extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }
        $userId = (int) (auth()->user()?->getAuthIdentifier() ?? 0);
        if ($userId === 0) {
            return Response::error('Unauthenticated.');
        }

        $entries = collect();

        Issue::query()
            ->where('workspace_id', $workspace->id)
            ->where('assignee_user_id', $userId)
            ->whereNull('archived_at')
            ->with(['team:id,key,name', 'workflowState:id,name,type'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->each(function (Issue $i) use (&$entries) {
                $entries->push([
                    'kind' => 'assigned',
                    'occurred_at' => $i->updated_at?->toIso8601String(),
                    'identifier' => ($i->team?->key ?? '?').'-'.$i->number,
                    'title' => $i->title,
                    'state' => $i->workflowState?->name,
                    'snippet' => null,
                    'url' => '/issues/'.($i->team?->key ?? '?').'-'.$i->number,
                ]);
            });

        Comment::query()
            ->whereHas('issue', function ($q) use ($workspace, $userId) {
                $q->where('workspace_id', $workspace->id)
                    ->where(fn ($qq) => $qq->where('assignee_user_id', $userId)
                        ->orWhere('creator_user_id', $userId));
            })
            ->where('user_id', '!=', $userId)
            ->with(['user:id,name', 'issue.team:id,key'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->each(function (Comment $c) use (&$entries) {
                if ($c->issue === null) return;
                $entries->push([
                    'kind' => 'commented',
                    'occurred_at' => $c->created_at?->toIso8601String(),
                    'identifier' => ($c->issue->team?->key ?? '?').'-'.$c->issue->number,
                    'title' => $c->issue->title,
                    'actor' => $c->user?->name,
                    'snippet' => mb_substr(strip_tags((string) $c->body), 0, 200),
                    'url' => '/issues/'.($c->issue->team?->key ?? '?').'-'.$c->issue->number,
                ]);
            });

        $sorted = $entries
            ->filter(fn ($e) => $e['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->values()
            ->take(30)
            ->all();

        return Response::json(['count' => count($sorted), 'feed' => $sorted]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_slug' => $schema->string(),
        ];
    }
}
