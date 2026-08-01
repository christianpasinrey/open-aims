<?php

declare(strict_types=1);

namespace App\Modules\Initiatives\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Initiatives\Models\Initiative;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a workspace-level initiative grouping projects under a goal. '
    .'Initiatives sit above projects: create the initiative first, then create its '
    .'projects with `projects-create`. `projects-get` reports the initiative a project '
    .'rolls up to, and `initiatives-list` reports how many projects each one holds.'
)]
class InitiativesCreate extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error($this->workspaceError());
        }
        $user = auth()->user();

        $data = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'state' => 'nullable|string|in:planned,active,completed,canceled',
            'owner' => 'nullable|string|max:255',
            'target_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'color' => 'nullable|string|max:32',
            'icon' => 'nullable|string|max:64',
        ])->validate();

        $ownerId = $this->resolveWorkspaceMember(
            $data['owner'] ?? null, $workspace, (int) $user?->getAuthIdentifier(),
        );
        if ($ownerId === false) {
            return Response::error(
                "Owner '{$data['owner']}' is not a member of workspace '{$workspace->slug}'."
            );
        }

        $slug = Str::slug($data['name']).'-'.Str::random(6);

        $initiative = Initiative::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'state' => $data['state'] ?? 'planned',
            'owner_user_id' => $ownerId,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ]);

        return Response::json([
            'slug' => $initiative->slug,
            'name' => $initiative->name,
            'url' => '/initiatives/'.$initiative->slug,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->description('The goal the grouped projects serve.'),
            'state' => $schema->string()->description('planned|active|completed|canceled'),
            'owner' => $schema->string()->description('"me", numeric user id, or email. Must be a member of the workspace.'),
            'start_date' => $schema->string(),
            'target_date' => $schema->string(),
            'color' => $schema->string(),
            'icon' => $schema->string(),
            'workspace_slug' => $schema->string()->description(
                'Workspace slug. Omit only when the user belongs to a single workspace — when omitted '
                .'the FIRST membership by id is used, which may not be the one the user means. '
                .'Get valid slugs from the `current` tool.'
            ),
        ];
    }
}
