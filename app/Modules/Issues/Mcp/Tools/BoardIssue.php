<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'One issue for a client that renders an interface: the `board-issues` row plus the markdown '
    .'description, comments (oldest first), parent, sub-issues, the workflow states of its team (to '
    .'offer a status change), the branch to work on, and the workspace slug. Agents that need the '
    .'plan, relations or graph should use `issues-get`.'
)]
class BoardIssue extends Tool
{
    use PresentsBoardIssues;
    use ResolvesIssueRefs;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
        ])->validate();

        [$found, $error] = $this->findIssueByIdentifier($workspace, $data['identifier']);
        if ($found === null) {
            return Response::error((string) $error);
        }

        $issue = Issue::query()
            ->with([
                ...$this->boardIssueRelations(),
                'parent:id,team_id,number,title',
                'parent.team:id,key',
                'children' => fn ($q) => $q->whereNull('archived_at')->orderBy('number'),
                'children.team:id,key',
                'children.workflowState:id,name,type,color',
                'comments' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                'comments.user:id,name',
            ])
            ->findOrFail($found->getKey());

        $states = WorkflowState::query()
            ->where('team_id', $issue->team_id)
            ->orderBy('position')
            ->get();

        return Response::json([
            ...$this->boardIssueRow($issue),
            'description' => $issue->description,
            'created_at' => $issue->created_at?->toIso8601String(),
            'comments' => $issue->comments->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'author' => $comment->user === null ? null : [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                ],
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->all(),
            'parent' => $issue->parent === null ? null : [
                'identifier' => $this->issueIdentifier($issue->parent),
                'title' => $issue->parent->title,
            ],
            'children' => $issue->children->map(fn (Issue $child): array => [
                'identifier' => $this->issueIdentifier($child),
                'title' => $child->title,
                'state' => $child->workflowState === null ? null : [
                    'id' => $child->workflowState->id,
                    'name' => $child->workflowState->name,
                    'type' => $child->workflowState->type,
                    'color' => $child->workflowState->color,
                ],
            ])->all(),
            'states' => $states->map(fn (WorkflowState $state): array => $this->boardStateRow($state))->all(),
            'branch' => $this->issueBranchName($issue),
            'workspace_slug' => $workspace->slug,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required()->description('Issue id (e.g. "LAM-275").'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
