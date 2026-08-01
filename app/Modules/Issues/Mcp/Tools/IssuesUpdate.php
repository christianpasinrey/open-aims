<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
use App\Modules\Issues\Support\IssueActivityRecorder;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\WorkflowState;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Partial update of an issue. Send only the fields you want to change. '
    .'State transitions auto-set started_at / completed_at / canceled_at. '
    .'Supports Scrum fields: `estimate` (story points) and `parent` (issue identifier — '
    .'set to attach the issue to an epic, pass null to detach). '
    .'Always attach a plan unless skip_plan is true. Plans live with the issue, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html") to refresh '
    .'the issue plan; previous plan rows are preserved as history but flagged inactive. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'DIAGRAMS REQUIRE plan_format="html": Mermaid and Chart.js only render for HTML plans. '
    .'A markdown plan silently renders NO diagrams even if plan_libs is set, so pass '
    .'plan_format="html" + plan_libs (e.g. ["mermaid"]) whenever the plan contains a diagram or chart; '
    .'you may also load your own external CDNs inside the HTML if needed. '
    .'Label names that do not exist in the team are NOT created — the response reports them in '
    .'`labels_unknown` (use `labels-ensure` to create them first). '
    .'Returns `labels_applied` and `labels_unknown`.'
)]
class IssuesUpdate extends Tool
{
    use AttachesPlan;
    use ResolvesIssueRefs;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }
        $user = auth()->user();

        $data = Validator::make($request->all(), [
            'identifier' => 'required|string|regex:/^[A-Za-z]+-\d+$/',
            'title' => 'sometimes|required|string|max:500',
            'description' => 'sometimes|nullable|string',
            'state' => 'sometimes|nullable|string|max:64',
            'priority' => 'sometimes|integer|min:0|max:4',
            'assignee' => 'sometimes|nullable|string|max:255',
            'project_slug' => 'sometimes|nullable|string|max:200',
            'cycle_number' => 'sometimes|nullable|integer|min:1',
            'estimate' => 'sometimes|nullable|numeric|min:0',
            'parent' => 'sometimes|nullable|string|regex:/^[A-Za-z]+-\d+$/',
            'due_date' => 'sometimes|nullable|date',
            'labels' => 'sometimes|array',
            'labels.*' => 'string|max:64',
            'plan_content' => 'sometimes|nullable|string',
            'plan_format' => 'sometimes|nullable|string|in:md,html',
            'plan_libs' => 'sometimes|nullable|array|prohibited_unless:plan_format,html',
            'plan_libs.*' => 'string|in:mermaid,chart',
            'skip_plan' => 'sometimes|nullable|boolean',
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

        $changes = [];
        if (array_key_exists('title', $data)) {
            $changes['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $changes['description'] = $data['description'];
        }
        if (array_key_exists('priority', $data)) {
            $changes['priority'] = (int) $data['priority'];
        }
        if (array_key_exists('estimate', $data)) {
            $changes['estimate'] = $data['estimate'];
        }
        if (array_key_exists('due_date', $data)) {
            $changes['due_date'] = $data['due_date'];
        }

        if (array_key_exists('state', $data)) {
            $stateId = $data['state'] === null ? null : WorkflowState::query()
                ->where('team_id', $team->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($data['state'])])
                ->value('id');
            if ($data['state'] !== null && $stateId === null) {
                return Response::error("State '{$data['state']}' not found in team {$key}.");
            }
            if ($stateId !== null) {
                $newState = WorkflowState::find($stateId);
                $changes['workflow_state_id'] = $stateId;
                if ($newState->type === 'started' && $issue->started_at === null) {
                    $changes['started_at'] = now();
                } elseif ($newState->type === 'completed') {
                    $changes['completed_at'] = now();
                } elseif ($newState->type === 'canceled') {
                    $changes['canceled_at'] = now();
                }
            }
        }

        if (array_key_exists('assignee', $data)) {
            $assigneeId = $this->resolveWorkspaceMember(
                $data['assignee'],
                $workspace,
                (int) $user?->getAuthIdentifier(),
            );
            if ($assigneeId === false) {
                return Response::error(sprintf(
                    "Assignee '%s' is not a member of workspace '%s'. Only workspace members can be assigned.",
                    (string) $data['assignee'],
                    $workspace->slug,
                ));
            }
            $changes['assignee_user_id'] = $assigneeId;
        }

        if (array_key_exists('parent', $data)) {
            if (empty($data['parent'])) {
                $changes['parent_issue_id'] = null;
            } else {
                [$parent, $parentError] = $this->findIssueByIdentifier($workspace, $data['parent']);
                if ($parent === null) {
                    return Response::error('Parent issue: '.$parentError);
                }
                if ($parent->getKey() === $issue->getKey()) {
                    return Response::error('An issue cannot be its own parent.');
                }
                $changes['parent_issue_id'] = $parent->getKey();
            }
        }

        if (array_key_exists('project_slug', $data)) {
            $changes['project_id'] = $data['project_slug']
                ? Project::query()->where('workspace_id', $workspace->id)->where('slug', $data['project_slug'])->value('id')
                : null;
        }

        if (array_key_exists('cycle_number', $data)) {
            $changes['cycle_id'] = $data['cycle_number']
                ? Cycle::query()->where('team_id', $team->id)->where('number', (int) $data['cycle_number'])->value('id')
                : null;
        }

        $recorder = app(IssueActivityRecorder::class);
        $snapshot = $recorder->snapshot($issue);

        $issue->fill($changes)->save();

        $labels = ['ids' => [], 'applied' => [], 'unknown' => []];
        if (array_key_exists('labels', $data)) {
            $labels = $this->resolveTeamLabels((int) $team->id, $data['labels']);
            $issue->labels()->sync($labels['ids']);
        }

        $recorder->record(
            $issue->fresh(['labels']),
            $snapshot['before'],
            $snapshot['labelIds'],
            $user?->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
        );

        $planSummary = null;
        if ($planContent !== null && $planContent !== '') {
            $plan = $this->attachPlan(
                $issue, $planContent, $planFormat, $planLibs,
                $user?->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
            );
            $planSummary = $this->planSummary($plan);
        }

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'updated_fields' => array_merge(
                array_keys($changes),
                array_key_exists('labels', $data) ? ['labels'] : [],
            ),
            'labels_applied' => $labels['applied'],
            'labels_unknown' => $labels['unknown'],
            'url' => '/issues/'.$team->key.'-'.$issue->number,
            'plan' => $planSummary,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->required()->description('Issue id (e.g. "LAM-275").'),
            'title' => $schema->string(),
            'description' => $schema->string(),
            'state' => $schema->string()->description('State name to transition to.'),
            'priority' => $schema->integer(),
            'assignee' => $schema->string()->description(
                '"me", numeric user id, or email. Must be a member of the target workspace. '
                .'Pass null to unassign.'
            ),
            'project_slug' => $schema->string(),
            'cycle_number' => $schema->integer(),
            'estimate' => $schema->number()->description('Story points for Scrum planning (e.g. 1, 2, 3, 5, 8).'),
            'parent' => $schema->string()->description(
                'Parent issue identifier (e.g. "LAM-275") to attach this issue to an epic. '
                .'Must live in the same workspace. Pass null to detach.'
            ),
            'due_date' => $schema->string()->description('YYYY-MM-DD.'),
            'labels' => $schema->array()->items($schema->string())->description(
                'Label names — replaces the current set. They must already exist in the team; unknown '
                .'names are reported back in `labels_unknown` and are NOT created. Use `labels-ensure` first.'
            ),
            'plan_content' => $schema->string()->description(
                'Full plan body. Markdown or HTML depending on plan_format. '
                .'Required unless skip_plan=true.'
            ),
            'plan_format' => $schema->string()->description(
                '"md" (default) or "html". Required if plan_content is set. '
                .'Use "html" for ANY plan containing diagrams or charts — plan_libs is ignored for "md".'
            ),
            'plan_libs' => $schema->array()->items($schema->string())->description(
                'Local libraries to inject into the rendered plan iframe (no CDN needed): '
                .'"mermaid" (diagrams: graph/sequence/gantt — write <pre class="mermaid">...</pre>), '
                .'"chart" (Chart.js — write a <canvas id="..."> and a small init script). '
                .'REQUIRES plan_format="html". With plan_format="md" the renderer skips the iframe entirely '
                .'and NO diagram is drawn.'
            ),
            'skip_plan' => $schema->boolean()->description('Set true to bypass the plan requirement (default false).'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — '
                .'when omitted the FIRST membership by id is used, which may not be the one '
                .'the user means. Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
