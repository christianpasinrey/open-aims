<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Support\ProjectActivityRecorder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Partial update of a project. Send only the fields you want to change. '
    .'state=completed auto-sets completed_at; state changing away from '
    .'completed clears it. '
    .'Always attach a plan unless skip_plan is true. Plans live with the project, not in the codebase. '
    .'Pass `plan_content` (markdown or HTML body) and `plan_format` ("md" or "html") to refresh '
    .'the project plan; previous plan rows are preserved as history but flagged inactive. '
    .'Plans render in an isolated sandboxed iframe (scripts run but cannot access the AIMS session/API). '
    .'Mermaid diagrams and Chart.js charts REQUIRE plan_format="html" — the renderer skips '
    .'plan_libs entirely for markdown plans, so a diagram in a "md" plan silently renders nothing. '
    .'For diagrams/charts pass plan_format="html" + plan_libs (e.g. ["mermaid"]) and use the documented markup; '
    .'you may also load your own external CDNs inside the HTML if needed. '
    .'Goal and Scope live inside `description` (headings "## Goal" / "## Scope"); read the current '
    .'description with `projects-get` first so you do not drop them when you rewrite it.'
)]
class ProjectsUpdate extends Tool
{
    use AttachesPlan;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }
        $user = auth()->user();

        $data = Validator::make($request->all(), [
            'slug' => 'required|string|max:200',
            'name' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string',
            'state' => 'sometimes|nullable|string|in:backlog,planned,started,paused,completed,canceled',
            'lead' => 'sometimes|nullable|string|max:255',
            'color' => 'sometimes|nullable|string|max:32',
            'icon' => 'sometimes|nullable|string|max:64',
            'start_date' => 'sometimes|nullable|date',
            'target_date' => 'sometimes|nullable|date',
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

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $data['slug'])
            ->first();
        if ($project === null) {
            return Response::error("Project '{$data['slug']}' not found.");
        }

        $changes = [];
        foreach (['name', 'description', 'color', 'icon', 'start_date', 'target_date'] as $f) {
            if (array_key_exists($f, $data)) {
                $changes[$f] = $data[$f];
            }
        }

        if (array_key_exists('state', $data)) {
            $changes['state'] = $data['state'];
            if ($data['state'] === 'completed') {
                $changes['completed_at'] = now();
            } elseif ($project->completed_at !== null) {
                $changes['completed_at'] = null;
            }
        }

        if (array_key_exists('lead', $data)) {
            $leadId = $this->resolveWorkspaceMember(
                $data['lead'], $workspace, (int) $user?->getAuthIdentifier(),
            );
            if ($leadId === false) {
                return Response::error(
                    "Lead '{$data['lead']}' is not a member of workspace '{$workspace->slug}'."
                );
            }
            $changes['lead_user_id'] = $leadId;
        }

        $recorder = app(ProjectActivityRecorder::class);
        $snapshot = $recorder->snapshot($project);

        $project->fill($changes)->save();

        $recorder->record(
            $project->fresh(),
            $snapshot,
            $user?->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null,
        );

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
            'updated_fields' => array_keys($changes),
            'url' => '/projects/'.$project->slug,
            'plan' => $planSummary,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->required(),
            'name' => $schema->string(),
            'description' => $schema->string()->description(
                'Replaces the whole description, including any "## Goal" / "## Scope" sections '
                .'written by `projects-create`. Read the current value first and re-send those '
                .'sections unless you mean to remove them.'
            ),
            'state' => $schema->string(),
            'lead' => $schema->string()->description('"me", numeric user id, or email. Must be a member of the workspace.'),
            'color' => $schema->string(),
            'icon' => $schema->string(),
            'start_date' => $schema->string(),
            'target_date' => $schema->string(),
            'plan_content' => $schema->string()->description(
                'Full plan body. Markdown or HTML depending on plan_format. '
                .'Required unless skip_plan=true.'
            ),
            'plan_format' => $schema->string()->description(
                '"md" (default) or "html". Required if plan_content is set. '
                .'Must be "html" for any plan containing Mermaid diagrams or Chart.js charts.'
            ),
            'plan_libs' => $schema->array()->items($schema->string())->description(
                'Local libraries to inject into the rendered plan iframe (no CDN needed): '
                .'"mermaid" (diagrams: graph/sequence/gantt — write <pre class="mermaid">...</pre>), '
                .'"chart" (Chart.js — write a <canvas id="..."> and a small init script). '
                .'REQUIRES plan_format="html": the renderer ignores plan_libs for markdown plans, '
                .'so a Mermaid or Chart.js plan sent as "md" renders no diagram at all.'
            ),
            'skip_plan' => $schema->boolean()->description('Set true to bypass the plan requirement (default false).'),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
