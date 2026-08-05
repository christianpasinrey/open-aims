<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Support\WorkspaceProvisioner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Create a NEW workspace owned by the authenticated user. This is the only '
    .'tool that does not operate inside an existing workspace — it makes one. '
    .'A workspace is unusable without a team, so an initial team (with the six '
    .'default workflow states) is created alongside it; name it with `team_name` '
    .'/ `team_key` or it takes the workspace name. The slug is generated, never '
    .'chosen: use the `slug` in the response as `workspace_slug` on every later '
    .'call. Creating a workspace is not how you switch boards — to work in one '
    .'that already exists, call `workspaces-list` and pass its slug instead.'
)]
class WorkspacesCreate extends Tool
{
    public function handle(Request $request): Response
    {
        $user = auth()->user();
        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $name = trim((string) $request->get('name'));
        if ($name === '') {
            return Response::error('name is required.');
        }
        if (mb_strlen($name) > 60) {
            return Response::error('name must be 60 characters or fewer.');
        }

        $joinPolicy = $request->get('join_policy');
        if (is_string($joinPolicy) && $joinPolicy !== '' && ! in_array($joinPolicy, WorkspaceProvisioner::JOIN_POLICIES, true)) {
            return Response::error(
                "Unknown join_policy '{$joinPolicy}'. Use one of: ".implode(', ', WorkspaceProvisioner::JOIN_POLICIES).'.'
            );
        }

        $provisioner = app(WorkspaceProvisioner::class);
        if ($provisioner->hasReachedLimit($user)) {
            return Response::error(sprintf(
                'You already own the maximum of %d workspaces. Delete one or use an existing workspace (see `workspaces-list`).',
                $provisioner->limitPerUser(),
            ));
        }

        $workspace = $provisioner->create(
            $user,
            $name,
            is_string($joinPolicy) ? $joinPolicy : null,
            is_string($request->get('team_name')) ? $request->get('team_name') : null,
            is_string($request->get('team_key')) ? $request->get('team_key') : null,
        );

        $team = Team::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->first();

        return Response::json([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'join_policy' => $workspace->join_policy,
            'role' => 'owner',
            'team' => $team === null ? null : ['key' => $team->key, 'name' => $team->name],
            'next' => "Pass workspace_slug='{$workspace->slug}' on every call meant for this workspace.",
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Workspace name (<=60 chars). The slug is derived from it and made unique.'),
            'join_policy' => $schema->string()->description("How other users get in: 'open' (anyone joins), 'request' (they ask, an admin approves — the default), 'private' (invite only)."),
            'team_name' => $schema->string()->description('Name of the initial team. Defaults to the workspace name.'),
            'team_key' => $schema->string()->description('Uppercase key (<=8) prefixing issue identifiers, e.g. ENG → ENG-1. Auto-generated from the team name if omitted.'),
        ];
    }
}
