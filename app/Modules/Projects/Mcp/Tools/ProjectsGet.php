<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Fetch a project by slug with full detail: description, lead, members, '
    .'milestones, linked teams, issue counts, progress.'
)]
class ProjectsGet extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
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
            'milestones' => $project->milestones->map(fn ($ms) => [
                'id' => $ms->id,
                'name' => $ms->name,
                'description' => $ms->description,
                'target_date' => $ms->target_date,
            ])->all(),
            'teams' => $project->teams->pluck('key')->all(),
            'total_issues' => $total,
            'completed_issues' => $completed,
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'url' => '/projects/'.$project->slug,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->required(),
            'workspace_slug' => $schema->string(),
        ];
    }
}
