<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Cycles\Models\Cycle;
use App\Modules\Issues\Models\Issue;
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
    .'project_slug, cycle_number, labels (string[]).'
)]
class IssuesCreate extends Tool
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
        ])->validate();

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

        return Response::json([
            'identifier' => $team->key.'-'.$issue->number,
            'title' => $issue->title,
            'url' => '/issues/'.$team->key.'-'.$issue->number,
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
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
