<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\AttachesPlan;
use App\Core\Mcp\ResolvesWorkspace;
use App\Models\Plan;
use App\Modules\Initiatives\Models\Initiative;
use App\Modules\Issues\Models\Issue;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Fetch a project by slug with its full planning graph, so a caller can see the '
    .'structure without extra round trips: description, lead, members, linked teams, '
    .'issue counts and progress, plus `initiative` (the initiative this project rolls '
    .'up to, or null), `milestones` (name, description, target_date, completed_at) and '
    .'`issue_breakdown` (issue counts grouped by workflow state, with the state type). '
    .'Also returns the latest plan attached to the project — `plan` (summary) '
    .'and `plan_full_content` (full markdown/HTML body) so future Claude '
    .'sessions can read the plan without scanning the codebase. Note that any '
    .'Mermaid or Chart.js content in a plan only renders when the plan was stored '
    .'with plan_format="html"; markdown plans never render diagrams.'
)]
class ProjectsGet extends Tool
{
    use AttachesPlan;
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }

        $data = Validator::make($request->all(), [
            'slug' => 'required|string|max:200',
        ])->validate();

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $data['slug'])
            ->with([
                'lead:id,name,email',
                'members.user:id,name,email',
                'milestones',
                'teams:id,key,name,color',
            ])
            ->withCount([
                'issues as total_issues',
                'issues as completed_issues' => fn ($q) => $q->whereHas(
                    'workflowState',
                    fn ($w) => $w->where('type', 'completed'),
                ),
            ])
            ->first();
        if ($project === null) {
            return Response::error("Project '{$data['slug']}' not found.");
        }

        $description = $project->description;
        $truncated = false;
        if ($description !== null && mb_strlen($description) > 4000) {
            $description = mb_substr($description, 0, 4000)."\n\n…(truncated)";
            $truncated = true;
        }

        $total = (int) $project->total_issues;
        $completed = (int) $project->completed_issues;

        $plan = Plan::query()
            ->where('planable_type', $project->getMorphClass())
            ->where('planable_id', $project->getKey())
            ->where('is_current', true)
            ->latest('id')
            ->first();
        $planSummary = $this->planSummary($plan);
        $planFullContent = $this->planFullContent($plan);

        return Response::json([
            'slug' => $project->slug,
            'name' => $project->name,
            'description' => $description,
            'description_truncated' => $truncated,
            'state' => $project->state?->value,
            'icon' => $project->icon,
            'color' => $project->color,
            'start_date' => $project->start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'lead' => $project->lead ? [
                'id' => $project->lead->id,
                'name' => $project->lead->name,
                'email' => $project->lead->email,
            ] : null,
            'members' => $project->members->map(fn ($m) => [
                'id' => $m->user?->id,
                'name' => $m->user?->name,
                'role' => $m->role,
            ])->filter(fn ($m) => $m['id'] !== null)->values()->all(),
            'initiative' => $this->initiativeOf($project),
            'milestones' => $project->milestones->map(fn ($ms) => [
                'id' => $ms->id,
                'name' => $ms->name,
                'description' => $ms->description,
                'target_date' => $ms->target_date?->toDateString(),
                'completed_at' => $ms->completed_at?->toIso8601String(),
            ])->all(),
            'teams' => $project->teams->pluck('key')->all(),
            'total_issues' => $total,
            'completed_issues' => $completed,
            'issue_breakdown' => $this->issueBreakdown($project),
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'plan' => $planSummary,
            'plan_full_content' => $planFullContent,
            'url' => '/projects/'.$project->slug,
        ]);
    }

    /**
     * The initiative this project rolls up to, or null. Projects link to
     * initiatives through the `initiative_projects` pivot; in practice a
     * project belongs to at most one, so the first is returned.
     *
     * @return array{slug:string,name:string,state:?string,target_date:?string,url:string}|null
     */
    private function initiativeOf(Project $project): ?array
    {
        $initiative = Initiative::query()
            ->where('workspace_id', $project->workspace_id)
            ->whereHas('projects', fn ($q) => $q->where('projects.id', $project->getKey()))
            ->orderBy('id')
            ->first();

        if ($initiative === null) {
            return null;
        }

        return [
            'slug' => $initiative->slug,
            'name' => $initiative->name,
            'state' => $initiative->state?->value,
            'target_date' => $initiative->target_date?->toDateString(),
            'url' => '/initiatives/'.$initiative->slug,
        ];
    }

    /**
     * Issue counts grouped by workflow state, so a caller can see where the
     * work actually sits without a second issues-list call.
     *
     * @return array<int,array{state:string,type:?string,count:int}>
     */
    private function issueBreakdown(Project $project): array
    {
        $issues = Issue::query()
            ->where('project_id', $project->getKey())
            ->whereNull('archived_at')
            ->with('workflowState:id,name,type,position')
            ->get();

        return $issues
            ->groupBy(fn (Issue $i) => $i->workflowState?->name ?? 'Unknown')
            ->map(fn ($group, string $name) => [
                'state' => $name,
                'type' => $group->first()->workflowState?->type,
                'position' => (int) ($group->first()->workflowState?->position ?? 0),
                'count' => $group->count(),
            ])
            ->sortBy('position')
            ->values()
            ->map(fn (array $row) => [
                'state' => $row['state'],
                'type' => $row['type'],
                'count' => $row['count'],
            ])
            ->all();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->required(),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
