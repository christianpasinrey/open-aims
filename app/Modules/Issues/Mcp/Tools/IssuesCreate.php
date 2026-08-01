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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a new issue in a team. Auto-numbers within the team. The '
    .'creator is the authenticated user. Optional fields: description '
    .'(markdown), priority (0..4), state name, assignee ("me"|user_id|email — must '
    .'be a member of the workspace), project_slug, cycle_number, labels (string[]), '
    .'estimate (story points), parent (issue identifier, to break an epic into stories '
    .'in one call). '
    .'SCRUM GATE: `acceptance_criteria` (Gherkin lines) is REQUIRED unless skip_scrum=true; '
    .'the lines are appended to the description under a "## Acceptance criteria" heading. '
    .'Always attach a plan unless skip_plan is true. Plans live with the issue, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html"). '
    .'The plan is stored with the issue and rendered inline on the issue page; '
    .'future MCP read calls (issues.get) return the full plan body so later sessions '
    .'can pick up the work without scanning the repo. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'DIAGRAMS REQUIRE plan_format="html": Mermaid and Chart.js only render for HTML plans. '
    .'A markdown plan silently renders NO diagrams even if plan_libs is set, so pass '
    .'plan_format="html" + plan_libs (e.g. ["mermaid"]) whenever the plan contains a diagram or chart; '
    .'you may also load your own external CDNs inside the HTML if needed. '
    .'Label names that do not exist in the team are NOT created — the response reports them '
    .'in `labels_unknown` (use `labels-ensure` to create them first). '
    .'Returns `labels_applied` and `labels_unknown`.'
)]
class IssuesCreate extends Tool
{
    use AttachesPlan;
    use ResolvesIssueRefs;
    use ResolvesWorkspace;

    private const GHERKIN_EXAMPLE =
        'Given a logged-in reviewer, When they open a closed issue, Then the reopen button is visible.';

    /**
     * Gherkin keywords in order. Spanish is accepted alongside English because
     * this workspace writes its issues in Spanish, and an English-only pattern
     * would reject "Dado que … Cuando … Entonces …" — a correct criterion.
     */
    private const GHERKIN_PATTERN = '/\b(?:given|dado)\b.*\b(?:when|cuando)\b.*\b(?:then|entonces)\b/iu';

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
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
            'estimate' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|regex:/^[A-Za-z]+-\d+$/',
            'labels' => 'nullable|array',
            'labels.*' => 'string|max:64',
            'acceptance_criteria' => 'nullable|array',
            'acceptance_criteria.*' => 'string|max:1000',
            'skip_scrum' => 'nullable|boolean',
            'plan_content' => 'nullable|string',
            'plan_format' => 'nullable|string|in:md,html',
            'plan_libs' => 'nullable|array|prohibited_unless:plan_format,html',
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

        $skipScrum = (bool) ($data['skip_scrum'] ?? false);
        $criteria = array_values(array_filter(
            array_map('trim', $data['acceptance_criteria'] ?? []),
            static fn (string $line): bool => $line !== '',
        ));

        if (! $skipScrum && $criteria === []) {
            return Response::error(
                'acceptance_criteria is required. Every issue must state how it will be '
                .'verified, as one or more Gherkin lines. Example: "'.self::GHERKIN_EXAMPLE.'" '
                .'Pass skip_scrum=true only for throwaway or purely administrative issues.'
            );
        }

        foreach ($criteria as $line) {
            if (preg_match(self::GHERKIN_PATTERN, $line) !== 1) {
                return Response::error(
                    'Each acceptance_criteria entry must be a Gherkin line containing '
                    .'Given, When and Then in that order (Spanish "Dado / Cuando / Entonces" '
                    .'is accepted too). Offending line: "'.$line.'". '
                    .'Example: "'.self::GHERKIN_EXAMPLE.'"'
                );
            }
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

        $assigneeId = $this->resolveWorkspaceMember(
            $data['assignee'] ?? null,
            $workspace,
            (int) $user->getAuthIdentifier(),
        );
        if ($assigneeId === false) {
            return Response::error(sprintf(
                "Assignee '%s' is not a member of workspace '%s'. Only workspace members can be assigned.",
                (string) ($data['assignee'] ?? ''),
                $workspace->slug,
            ));
        }

        $parentId = null;
        if (! empty($data['parent'])) {
            [$parent, $parentError] = $this->findIssueByIdentifier($workspace, $data['parent']);
            if ($parent === null) {
                return Response::error('Parent issue: '.$parentError);
            }
            $parentId = $parent->getKey();
        }

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

        $description = $this->composeDescription($data['description'] ?? null, $criteria);

        $issue = DB::transaction(function () use ($team, $workspace, $user, $stateId, $assigneeId, $projectId, $cycleId, $parentId, $description, $data) {
            $team->refresh();
            $next = ((int) $team->issue_counter) + 1;
            $team->update(['issue_counter' => $next]);

            return Issue::create([
                'workspace_id' => $workspace->id,
                'team_id' => $team->id,
                'number' => $next,
                'title' => $data['title'],
                'description' => $description,
                'workflow_state_id' => $stateId,
                'priority' => $data['priority'] ?? 0,
                'assignee_user_id' => $assigneeId,
                'creator_user_id' => $user->getAuthIdentifier(),
                'project_id' => $projectId,
                'cycle_id' => $cycleId,
                'parent_issue_id' => $parentId,
                'estimate' => $data['estimate'] ?? null,
            ]);
        });

        $labels = ['ids' => [], 'applied' => [], 'unknown' => []];
        if (! empty($data['labels'])) {
            $labels = $this->resolveTeamLabels((int) $team->id, $data['labels']);
            if ($labels['ids'] !== []) {
                $issue->labels()->sync($labels['ids']);
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
            'estimate' => $issue->estimate,
            'parent' => $parentId !== null ? strtoupper(trim((string) $data['parent'])) : null,
            'acceptance_criteria' => $criteria,
            'labels_applied' => $labels['applied'],
            'labels_unknown' => $labels['unknown'],
            'url' => '/issues/'.$team->key.'-'.$issue->number,
            'plan' => $planSummary,
        ]);
    }

    /**
     * Acceptance criteria have no column of their own — they are rendered into
     * the issue description under a stable heading so the web UI, exports and
     * later MCP reads all see the same thing.
     *
     * @param  list<string>  $criteria
     */
    private function composeDescription(?string $description, array $criteria): ?string
    {
        $body = trim((string) $description);

        if ($criteria === []) {
            return $body === '' ? null : $body;
        }

        $section = "## Acceptance criteria\n\n".implode("\n", array_map(
            static fn (string $line): string => '- '.$line,
            $criteria,
        ));

        return $body === '' ? $section : $body."\n\n".$section;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'team_key' => $schema->string()->required()->description('Team key (e.g. "LAM").'),
            'title' => $schema->string()->required()->description('Issue title.'),
            'description' => $schema->string()->description(
                'Markdown description. Acceptance criteria are appended to it automatically.'
            ),
            'priority' => $schema->integer()->description('0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low.'),
            'state' => $schema->string()->description('State name (e.g. "Todo", "In Progress"). Defaults to first state.'),
            'assignee' => $schema->string()->description(
                '"me", numeric user id, or email. Must be a member of the target workspace.'
            ),
            'project_slug' => $schema->string()->description('Project slug from projects.list.'),
            'cycle_number' => $schema->integer()->description('Cycle number for this team.'),
            'estimate' => $schema->number()->description('Story points for Scrum planning (e.g. 1, 2, 3, 5, 8).'),
            'parent' => $schema->string()->description(
                'Parent issue identifier (e.g. "LAM-275") to create this issue as a sub-issue / story '
                .'of an epic. Must live in the same workspace.'
            ),
            'labels' => $schema->array()->items($schema->string())->description(
                'Label names. They must already exist in the team — unknown names are reported back in '
                .'`labels_unknown` and are NOT created. Use `labels-ensure` to create them first.'
            ),
            'acceptance_criteria' => $schema->array()->items($schema->string())->description(
                'Gherkin lines, one per criterion: "Given <context>, When <action>, Then <outcome>". '
                .'Spanish "Dado … Cuando … Entonces …" is accepted too. '
                .'Example: "'.self::GHERKIN_EXAMPLE.'" '
                .'REQUIRED unless skip_scrum=true. Rendered into the description under "## Acceptance criteria".'
            ),
            'skip_scrum' => $schema->boolean()->description(
                'Set true to bypass the acceptance_criteria requirement (default false).'
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
