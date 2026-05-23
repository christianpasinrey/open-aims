<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Support\ProjectActivityRecorder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a milestone to a project.')]
class ProjectsAddMilestone extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }

        $data = Validator::make($request->all(), [
            'project_slug' => 'required|string|max:200',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'target_date' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
        ])->validate();

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $data['project_slug'])
            ->first();
        if ($project === null) {
            return Response::error("Project '{$data['project_slug']}' not found.");
        }

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        app(ProjectActivityRecorder::class)->milestoneAdded($project, $milestone, auth()->id());

        return Response::json([
            'milestone_id' => $milestone->id,
            'project_slug' => $project->slug,
            'name' => $milestone->name,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_slug' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'description' => $schema->string(),
            'target_date' => $schema->string()->description('YYYY-MM-DD.'),
            'sort_order' => $schema->integer(),
            'workspace_slug' => $schema->string(),
        ];
    }
}
