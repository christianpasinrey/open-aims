<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Partial update of a project. Send only the fields you want to change. '
    .'state=completed auto-sets completed_at; state changing away from '
    .'completed clears it.'
)]
class ProjectsUpdate extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
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
        ])->validate();

        $project = Project::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', $data['slug'])
            ->first();
        if ($project === null) {
            return Response::error("Project '{$data['slug']}' not found.");
        }

        $changes = [];
        foreach (['name', 'description', 'color', 'icon', 'start_date', 'target_date'] as $f) {
            if (array_key_exists($f, $data)) $changes[$f] = $data[$f];
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
            $changes['lead_user_id'] = $data['lead']
                ? $this->resolveUser($data['lead'], (int) $user?->getAuthIdentifier())
                : null;
        }

        $project->fill($changes)->save();

        return Response::json([
            'slug' => $project->slug,
            'updated_fields' => array_keys($changes),
            'url' => '/projects/'.$project->slug,
        ]);
    }

    private function resolveUser(string $value, int $currentUserId): ?int
    {
        if ($value === 'me') return $currentUserId;
        if (is_numeric($value)) return (int) $value;
        $id = User::query()->where('email', $value)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->required(),
            'name' => $schema->string(),
            'description' => $schema->string(),
            'state' => $schema->string(),
            'lead' => $schema->string(),
            'color' => $schema->string(),
            'icon' => $schema->string(),
            'start_date' => $schema->string(),
            'target_date' => $schema->string(),
            'workspace_slug' => $schema->string(),
        ];
    }
}
