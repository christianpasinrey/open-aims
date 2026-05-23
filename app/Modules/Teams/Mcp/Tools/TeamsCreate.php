<?php

declare(strict_types=1);

namespace App\Modules\Teams\Mcp\Tools;

use App\Core\Mcp\ResolvesWorkspace;
use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Support\TeamProvisioner;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a team in the active workspace (owner/admin only). Seeds the default workflow states. Auto-generates a key from the name if none is given.')]
class TeamsCreate extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->bindWorkspace($request->get('workspace_slug'));
        if ($workspace === null) {
            return Response::error('No active workspace.');
        }
        $user = auth()->user();
        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $membership = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getAuthIdentifier())
            ->first();
        if ($membership === null || ! in_array($membership->role, ['owner', 'admin'], true)) {
            return Response::error('Only owners or admins can create teams.');
        }

        $name = trim((string) $request->get('name'));
        if ($name === '') {
            return Response::error('name is required.');
        }
        $key = $request->get('key');
        if (is_string($key) && $key !== '') {
            $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');
            $exists = Team::query()->where('workspace_id', $workspace->id)->where('key', $normalized)->exists();
            if ($exists) {
                return Response::error("Team key '{$normalized}' already exists in this workspace.");
            }
        }

        $team = app(TeamProvisioner::class)->create($workspace, $name, is_string($key) ? $key : null);

        return Response::json(['key' => $team->key, 'name' => $team->name]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Team name.'),
            'key' => $schema->string()->description('Optional uppercase key (<=8). Auto-generated if omitted.'),
            'workspace_slug' => $schema->string()->description('Optional workspace override.'),
        ];
    }
}
