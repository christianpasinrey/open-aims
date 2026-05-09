<?php

declare(strict_types=1);

namespace App\Modules\Projects\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Models\User;
use App\Modules\Projects\Models\Project;
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
    .'Auto-generates a unique slug from the name.'
)]
class ProjectsCreate extends Tool
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
        ])->validate();

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

        $project = DB::transaction(function () use ($workspace, $teams, $leadId, $slug, $data) {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'state' => $data['state'] ?? 'backlog',
                'lead_user_id' => $leadId,
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

        return Response::json([
            'slug' => $project->slug,
            'name' => $project->name,
            'url' => '/projects/'.$project->slug,
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
            'workspace_slug' => $schema->string(),
        ];
    }
}
