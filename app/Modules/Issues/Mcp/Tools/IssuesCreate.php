<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Support\IssueActivityRecorder;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Label;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a new issue in a team. Auto-numbers within the team. The '
    .'creator is the authenticated user. Optional fields: description '
    .'(markdown), priority (0..4), state name, assignee ("me"|user_id|email), '
    .'project_slug, cycle_number, labels (string[]). '
    .'Always attach a plan unless skip_plan is true. Plans live with the issue, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html"). '
    .'The plan is stored as an issue resource and rendered inline on the issue page; '
    .'future MCP read calls (issues.get) return the full plan body so later sessions '
    .'can pick up the work without scanning the repo. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'For diagrams/charts pass plan_format="html" + plan_libs (e.g. ["mermaid"]) and use the documented markup; '
    .'you may also load your own external CDNs inside the HTML if needed.'
)]
class IssuesCreate extends Tool
{
    use AttachesPlan;
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
            'team_key' => 'required|string|max:16',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'priority' => 'nullable|integer|min:0|max:4',
            'state' => 'nullable|string|max:64',
            'assignee' => 'nullable|string|max:255',
            'project_slug' => 'nullable|string|max:200',
            'cycle_number' => 'nullable|integer|min:1',
            'labels' => 'nullable|array',
            'labels.*' => 'string|max:64',
            'plan_content' => 'nullable|string',
            'plan_format' => 'nullable|string|in:md,html',
            'plan_libs' => 'nullable|array',
            'plan_libs.*' => 'string|in:mermaid,chart',
            'skip_plan' => 'nullable|boolean',
        ])->validate();

        $skipPlan = (bool) ($data['skip_plan'] ?? false);
        $planContent = isset($data['plan_content']) && is_string($data['plan_content'])
            ? $data['plan_content']
            : null;
        $planFormat = $data['plan_format'] ?? 'md';
        $planLibs = isset($data['plan_libs']) && is_array($data['plan_libs'])
            ? array_values(array_unique($data['plan_libs']))
            : null;

        if (! $skipPlan && ($planContent === null || $planContent === '')) {
            return Response::error(
                'Plan is required. Pass plan_content (markdown or HTML) or skip_plan=true.'
            );
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($data['team_key']))
            ->first();
        if ($team === null) {
            return Response::error("Team '{$data['team_key']}' not found.");
        }

        $stateId = ! empty($data['state'])
            ? WorkflowState::query()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($data['state'])])
                ->value('id')
            : WorkflowState::query()
                ->where('team_id', $team->id)
                ->orderBy('position')
                ->value('id');
        if ($stateId === null) {
            return Response::error("State '{$data['state']}' not found in team {$team->key}.");
        }

        $assigneeId = $this->resolveAssignee($data['assignee'] ?? null, (int) $user->getAuthIdentifier());

        $projectId = null;
        if (! empty($data['project_slug'])) {
            $projectId = Project::query()
                ->where('workspace_id', $workspace->id)
                ->where('slug', $data['project_slug'])
                ->value('id');
        }

        $cycleId = null;
        if (! empty($data['cycle_number'])) {
            $cycleId = Cycle::query()
                ->where('team_id', $team->id)
                ->where('number', (int) $data['cycle_number'])
                ->value('id');
        }

        $issue = DB::transaction(function () use ($team, $workspace, $user, $stateId, $assigneeId, $projectId, $cycleId, $data) {
            $team->refresh();
            $next = ((int) $team->issue_counter) + 1;
            $team->update(['issue_counter' => $next]);

            return Issue::create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'number' => $next,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'workflow_state_id' => $stateId,
                'priority' => $data['priority'] ?? 0,
                'assignee_user_id' => $assigneeId,
                'creator_user_id' => $user->getAuthIdentifier(),
                'project_id' => $projectId,
                'cycle_id' => $cycleId,
            ]);
        });

        if (! empty($data['labels'])) {
            $labelIds = Label::query()
                ->where('team_id', $team->id)
                ->whereIn('name', $data['labels'])
                ->pluck('id');
            if ($labelIds->isNotEmpty()) {
                $issue->labels()->sync($labelIds);
            }
        }

        app(IssueActivityRecorder::class)->created(
            $issue,
            $user->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
        );

        $planSummary = null;
        if ($planContent !== null && $planContent !== '') {
            $plan = $this->attachPlan(
                $issue, $planContent, $planFormat, $planLibs,
                $user->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
            );
            $planSummary = $this->planSummary($plan);
        }

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'title' => $issue->title,
            'url' => '/issues/'.$team->key.'-'.$issue->number,
            'plan' => $planSummary,
        ]);
    }

    private function resolveAssignee(?string $value, int $currentUserId): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === 'me') {
            return $currentUserId;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $id = User::query()->where('email', $value)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required()->description('Team key (e.g. "LAM").'),
            'title' => $schema->string()->required()->description('Issue title.'),
            'description' => $schema->string()->description('Markdown description.'),
            'priority' => $schema->integer()->description('0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low.'),
            'state' => $schema->string()->description('State name (e.g. "Todo", "In Progress"). Defaults to first state.'),
            'assignee' => $schema->string()->description('"me", numeric user id, or email.'),
            'project_slug' => $schema->string()->description('Project slug from projects.list.'),
            'cycle_number' => $schema->integer()->description('Cycle number for this team.'),
            'labels' => $schema->array()->items($schema->string())->description('Label names (must already exist in the team).'),
            'plan_content' => $schema->string()->description(
                'Full plan body. Markdown or HTML depending on plan_format. '
                .'Required unless skip_plan=true.'
            ),
            'plan_format' => $schema->string()->description('"md" (default) or "html". Required if plan_content is set.'),
            'plan_libs' => $schema->array()->items($schema->string())->description(
                'Local libraries to inject into the rendered plan iframe (no CDN needed): '
                .'"mermaid" (diagrams: graph/sequence/gantt — write <pre class="mermaid">...</pre>), '
                .'"chart" (Chart.js — write a <canvas id="..."> and a small init script). '
                .'Only valid with plan_format=html.'
            ),
            'skip_plan' => $schema->boolean()->description('Set true to bypass the plan requirement (default false).'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
