<?php

declare(strict_types=1);

namespace App\Modules\Issues\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Issues\Models\Issue;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List issues for a client that renders an interface (a panel, a board): each row carries ids and '
    .'colours for its state, team and labels, the priority as a number, and an absolute `url`. '
    .'Same filters as `issues-list`, plus `view: "mine"` (assigned to the caller) and `query` '
    .'(title text, or an identifier like "LAM-275"). No plan summaries. Agents reading for content '
    .'should prefer `issues-list`.'
)]
class BoardIssues extends Tool
{
    use PresentsBoardIssues;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }
        $user = auth()->user();

        $data = Validator::make($request->all(), [
            'view' => 'nullable|string|in:mine,all',
            'query' => 'nullable|string|max:200',
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
            ->with($this->boardIssueRelations());

        if (($data['view'] ?? 'all') === 'mine') {
            $query->where('assignee_user_id', $user?->getAuthIdentifier());
        }

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

        $search = trim((string) ($data['query'] ?? ''));
        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $issues = $query
            ->orderByRaw('CASE WHEN priority = 0 THEN 5 ELSE priority END')
            ->orderByDesc('updated_at')
            ->limit((int) ($data['limit'] ?? 50))
            ->get();

        return Response::json([
            'count' => $issues->count(),
            'issues' => $issues->map(fn (Issue $issue): array => $this->boardIssueRow($issue))->all(),
        ]);
    }

    /**
     * Title contains the text, or the identifier is exactly the one typed.
     * `%`, `_` and `!` are matched literally: this text comes from a search box.
     *
     * @param  Builder<Issue>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

        $query->where(function (Builder $q) use ($search, $like): void {
            $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$like]);

            if (preg_match('/^([A-Za-z]+)-(\d+)$/', $search, $m) === 1) {
                $q->orWhere(fn (Builder $byId) => $byId
                    ->where('number', (int) $m[2])
                    ->whereHas('team', fn ($team) => $team->where('key', strtoupper($m[1]))));
            }
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'view' => $schema->string()->description('"mine" = assigned to the caller; "all" (default).'),
            'query' => $schema->string()->description('Text to find in the title, or an identifier such as "LAM-275".'),
            'team' => $schema->string()->description('Team key (e.g. "LAM"). Omit for all teams.'),
            'state' => $schema->string()->description('Lifecycle type: triage|backlog|unstarted|started|completed|canceled'),
            'assignee' => $schema->string()->description('"me", "unassigned", a numeric user id, or an email.'),
            'priority' => $schema->integer()->description('0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low'),
            'labels' => $schema->array()->items($schema->string())->description('Label names; AND across labels.'),
            'project' => $schema->string()->description('Project slug.'),
            'cycle_number' => $schema->integer()->description('Cycle number.'),
            'limit' => $schema->integer()->description('Max results, default 50, at most 200.'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
