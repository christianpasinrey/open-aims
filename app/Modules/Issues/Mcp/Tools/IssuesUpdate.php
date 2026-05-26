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
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Partial update of an issue. Send only the fields you want to change. '
    .'State transitions auto-set started_at / completed_at / canceled_at. '
    .'Always attach a plan unless skip_plan is true. Plans live with the issue, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html") to refresh '
    .'the issue plan; previous plan rows are preserved as history but flagged inactive. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'For diagrams/charts pass plan_format="html" + plan_libs (e.g. ["mermaid"]) and use the documented markup; '
    .'you may also load your own external CDNs inside the HTML if needed.'
)]
class IssuesUpdate extends Tool
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
            'due_date' => 'sometimes|nullable|date',
            'labels' => 'sometimes|array',
            'labels.*' => 'string|max:64',
            'plan_content' => 'sometimes|nullable|string',
            'plan_format' => 'sometimes|nullable|string|in:md,html',
            'plan_libs' => 'sometimes|nullable|array',
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
            $changes['assignee_user_id'] = $this->resolveAssignee($data['assignee'], (int) $user?->getAuthIdentifier());
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

        if (array_key_exists('labels', $data)) {
            $labelIds = Label::query()
                ->where('team_id', $team->id)
                ->whereIn('name', $data['labels'])
                ->pluck('id');
            $issue->labels()->sync($labelIds);
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
            'updated_fields' => array_keys($changes) + (array_key_exists('labels', $data) ? ['labels'] : []),
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
            'identifier' => $schema->string()->required()->description('Issue id (e.g. "LAM-275").'),
            'title' => $schema->string(),
            'description' => $schema->string(),
            'state' => $schema->string()->description('State name to transition to.'),
            'priority' => $schema->integer(),
            'assignee' => $schema->string(),
            'project_slug' => $schema->string(),
            'cycle_number' => $schema->integer(),
            'estimate' => $schema->number(),
            'due_date' => $schema->string()->description('YYYY-MM-DD.'),
            'labels' => $schema->array()->items($schema->string()),
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
            'workspace_slug' => $schema->string(),
        ];
    }
}
