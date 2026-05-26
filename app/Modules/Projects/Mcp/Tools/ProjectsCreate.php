<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Support\ProjectActivityRecorder;
use App\Modules\Teams\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a new project. Attaches to one or more teams via `team_keys`. '
    .'Auto-generates a unique slug from the name. '
    .'Always attach a plan unless skip_plan is true. Plans live with the project, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html"). '
    .'The plan is stored as a project resource and rendered inline on the project page; '
    .'future MCP read calls (projects.get) return the full plan body so later sessions '
    .'can pick up the work without scanning the repo. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'For diagrams/charts pass plan_format="html" + plan_libs (e.g. ["mermaid"]) and use the documented markup; '
    .'you may also load your own external CDNs inside the HTML if needed.'
)]
class ProjectsCreate extends Tool
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
            'name' => 'required|string|max:200',
            'team_keys' => 'required|array|min:1',
            'team_keys.*' => 'string|max:16',
            'description' => 'nullable|string',
            'lead' => 'nullable|string|max:255',
            'state' => 'nullable|string|in:backlog,planned,started,paused,completed,canceled',
            'color' => 'nullable|string|max:32',
            'icon' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
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

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('key', array_map('strtoupper', $data['team_keys']))
            ->get();
        if ($teams->isEmpty()) {
            return Response::error('No valid teams found for the given keys.');
        }

        $leadId = null;
        if (! empty($data['lead'])) {
            $leadId = $this->resolveUser($data['lead'], (int) $user?->getAuthIdentifier());
        }

        $slug = Str::slug($data['name']).'-'.Str::random(6);

        $creatorId = $user?->getAuthIdentifier();

        $project = DB::transaction(function () use ($workspace, $teams, $leadId, $slug, $data, $creatorId) {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'state' => $data['state'] ?? 'backlog',
                'lead_user_id' => $leadId,
                'creator_user_id' => $creatorId,
                'color' => $data['color'] ?? '#6366f1',
                'icon' => $data['icon'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'target_date' => $data['target_date'] ?? null,
            ]);

            foreach ($teams as $team) {
                DB::table('project_teams')->updateOrInsert(
                    ['project_id' => $project->id, 'team_id' => $team->id],
                    [],
                );
            }

            return $project;
        });

        app(ProjectActivityRecorder::class)->created($project, $creatorId);

        $planSummary = null;
        if ($planContent !== null && $planContent !== '') {
            $plan = $this->attachPlan(
                $project, $planContent, $planFormat, $planLibs,
                $user?->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
            );
            $planSummary = $this->planSummary($plan);
        }

        return Response::json([
            'slug' => $project->slug,
            'name' => $project->name,
            'url' => '/projects/'.$project->slug,
            'plan' => $planSummary,
        ]);
    }

    private function resolveUser(string $value, int $currentUserId): ?int
    {
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
            'name' => $schema->string()->required(),
            'team_keys' => $schema->array()->items($schema->string())->required()->description('Team keys to attach this project to (e.g. ["LAM"]).'),
            'description' => $schema->string(),
            'lead' => $schema->string()->description('"me", numeric user id, or email.'),
            'state' => $schema->string()->description('backlog|planned|started|paused|completed|canceled'),
            'color' => $schema->string()->description('Hex color (#rrggbb).'),
            'icon' => $schema->string()->description('Emoji shortcode like ":rocket:".'),
            'start_date' => $schema->string(),
            'target_date' => $schema->string(),
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
