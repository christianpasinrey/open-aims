<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Post a markdown comment on an issue as the authenticated user.'
)]
class IssuesComment extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }
        $user = auth()->user();
        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'body' => 'required|string|max:20000',
        ])->validate();

        [$key, $number] = explode('-', strtoupper($data['identifier']));
        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', $key)
            ->first();
        if ($team === null) {
            return Response::error("Team '{$key}' not found.");
        }

        $issue = Issue::query()
            ->where('team_id', $team->id)
            ->where('number', (int) $number)
            ->first();
        if ($issue === null) {
            return Response::error("Issue {$data['identifier']} not found.");
        }

        $comment = Comment::create([
            'issue_id' => $issue->id,
            'user_id' => $user->getAuthIdentifier(),
            'parent_comment_id' => null,
            'body' => $data['body'],
        ]);

        return Response::json([
            'comment_id' => $comment->id,
            'identifier' => $team->key.'-'.$issue->number,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required(),
            'body' => $schema->string()->required()->description('Markdown body of the comment.'),
            'workspace_slug' => $schema->string(),
        ];
    }
}
