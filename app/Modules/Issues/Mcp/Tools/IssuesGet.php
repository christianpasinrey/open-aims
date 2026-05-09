<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Comment;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Models\IssueResource;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Fetch the full detail for a single issue: description (markdown), '
    .'state, priority, assignee, creator, project, cycle, labels, comments, '
    .'parent and sub-issues. Use the LAM-N identifier. '
    .'Also returns the latest plan attached to the issue — `plan` (summary) '
    .'and `plan_full_content` (full markdown/HTML body) so future Claude '
    .'sessions can read the plan without scanning the codebase.'
)]
class IssuesGet extends Tool
{
    use AttachesIssuePlan;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
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
            ->with([
                'workflowState:id,name,type,color',
                'assignee:id,name,email',
                'creator:id,name,email',
                'project:id,name,slug,color',
                'cycle:id,number,name,starts_at,ends_at',
                'labels:id,name,color',
                'parent:id,team_id,number,title',
                'children:id,team_id,parent_issue_id,number,title,workflow_state_id',
                'children.workflowState:id,name,type',
            ])
            ->first();
        if ($issue === null) {
            return Response::error("Issue {$data['identifier']} not found.");
        }

        $comments = Comment::query()
            ->where('issue_id', $issue->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        $description = $issue->description;
        $truncated = false;
        if ($description !== null && mb_strlen($description) > 4000) {
            $description = mb_substr($description, 0, 4000)."\n\n…(truncated)";
            $truncated = true;
        }

        $planResource = IssueResource::query()
            ->where('issue_id', $issue->id)
            ->where('is_plan', true)
            ->latest()
            ->first();
        $planSummary = $this->planSummary($planResource);
        $planFullContent = $this->planFullContent($planResource);

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'title' => $issue->title,
            'description' => $description,
            'description_truncated' => $truncated,
            'state' => $issue->workflowState?->name,
            'state_type' => $issue->workflowState?->type,
            'priority' => $issue->priority?->label(),
            'priority_value' => (int) ($issue->priority?->value ?? 0),
            'estimate' => $issue->estimate,
            'due_date' => $issue->due_date?->toDateString(),
            'started_at' => $issue->started_at?->toIso8601String(),
            'completed_at' => $issue->completed_at?->toIso8601String(),
            'canceled_at' => $issue->canceled_at?->toIso8601String(),
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
            ] : null,
            'cycle' => $issue->cycle ? [
                'number' => $issue->cycle->number,
                'name' => $issue->cycle->name,
            ] : null,
            'labels' => $issue->labels->pluck('name')->all(),
            'parent' => $issue->parent ? $team->key.'-'.$issue->parent->number : null,
            'children' => $issue->children->map(fn ($c) => [
                'identifier' => $team->key.'-'.$c->number,
                'title' => $c->title,
                'state' => $c->workflowState?->name,
            ])->all(),
            'git_branch_name' => $issue->git_branch_name ?? null,
            'comments' => $comments->map(fn (Comment $c) => [
                'user' => $c->user?->name,
                'body' => $c->body,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all(),
            'created_at' => $issue->created_at?->toIso8601String(),
            'updated_at' => $issue->updated_at?->toIso8601String(),
            'plan' => $planSummary,
            'plan_full_content' => $planFullContent,
            'url' => '/issues/'.$team->key.'-'.$issue->number,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required()
                ->description('Issue identifier (e.g. "LAM-275").'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
