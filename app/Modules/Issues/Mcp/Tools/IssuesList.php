<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List issues in the active workspace with rich filtering. Returns at '
    .'most `limit` items, ordered by priority then last update. Use '
    .'`team` to scope to a single team (e.g. "LAM"); omit for all teams. '
    .'Each issue includes a `plan` summary (or null) so callers can tell '
    .'at a glance which issues have a plan attached.'
)]
class IssuesList extends Tool
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
            'team' => 'nullable|string|max:16',
            'state' => 'nullable|string|in:triage,backlog,unstarted,started,completed,canceled',
            'assignee' => 'nullable|string',
            'priority' => 'nullable|integer|min:0|max:4',
            'labels' => 'nullable|array',
            'labels.*' => 'string',
            'project' => 'nullable|string',
            'cycle_number' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:200',
        ])->validate();

        $query = Issue::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->with([
                'team:id,key,name,color',
                'workflowState:id,name,type,color',
                'assignee:id,name,email',
                'project:id,name,slug',
                'labels:id,name,color',
                'plan',
            ]);

        if (! empty($data['team'])) {
            $teamId = Team::query()
                ->where('workspace_id', $workspace->id)
                ->where('key', strtoupper($data['team']))
                ->value('id');
            if ($teamId === null) {
                return Response::error("Team '{$data['team']}' not found.");
            }
            $query->where('team_id', $teamId);
        }

        if (! empty($data['state'])) {
            $query->whereHas('workflowState', fn ($q) => $q->where('type', $data['state']));
        }

        if (! empty($data['assignee'])) {
            $a = $data['assignee'];
            if ($a === 'me') {
                $query->where('assignee_user_id', $user?->getAuthIdentifier());
            } elseif ($a === 'unassigned') {
                $query->whereNull('assignee_user_id');
            } elseif (is_numeric($a)) {
                $query->where('assignee_user_id', (int) $a);
            } else {
                $query->whereHas('assignee', fn ($q) => $q->where('email', $a));
            }
        }

        if (isset($data['priority'])) {
            $query->where('priority', (int) $data['priority']);
        }

        if (! empty($data['labels'])) {
            foreach ($data['labels'] as $name) {
                $query->whereHas('labels', fn ($q) => $q->where('labels.name', $name));
            }
        }

        if (! empty($data['project'])) {
            $query->whereHas('project', fn ($q) => $q->where('slug', $data['project']));
        }

        if (! empty($data['cycle_number'])) {
            $query->whereHas('cycle', fn ($q) => $q->where('number', (int) $data['cycle_number']));
        }

        $limit = (int) ($data['limit'] ?? 50);
        $issues = $query
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return Response::json([
            'count' => $issues->count(),
            'issues' => $issues->map(fn (Issue $i) => [
                'identifier' => $i->team->key.'-'.$i->number,
                'title' => $i->title,
                'state' => $i->workflowState?->name,
                'state_type' => $i->workflowState?->type,
                'priority' => $i->priority?->label(),
                'assignee' => $i->assignee?->name,
                'project' => $i->project?->name,
                'labels' => $i->labels->pluck('name')->all(),
                'updated_at' => $i->updated_at?->toIso8601String(),
                'plan' => $this->planSummary($i->plan),
                'url' => '/issues/'.$i->team->key.'-'.$i->number,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team' => $schema->string()->description('Team key (e.g. "LAM"). Omit for all teams.'),
            'state' => $schema->string()->description('Lifecycle type: triage|backlog|unstarted|started|completed|canceled'),
            'assignee' => $schema->string()->description('"me", "unassigned", a numeric user id, or an email.'),
            'priority' => $schema->integer()->description('0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low'),
            'labels' => $schema->array()->items($schema->string())->description('Label names; AND across labels.'),
            'project' => $schema->string()->description('Project slug.'),
            'cycle_number' => $schema->integer()->description('Cycle number.'),
            'limit' => $schema->integer()->description('Max results, default 50.'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
