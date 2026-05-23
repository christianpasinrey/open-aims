<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Mcp\Tools;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'List every workspace the authenticated user belongs to (id, name, slug, '
    .'the user\'s role, and member count). Use this when the user has more than '
    .'one workspace, or to discover a slug to pass to workspace.current.'
)]
class WorkspacesList extends Tool
{
    public function handle(Request $request): Response
    {
        $user = auth()->user();
        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $roles = WorkspaceMember::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->pluck('role', 'workspace_id');

        if ($roles->isEmpty()) {
            return Response::json(['data' => []]);
        }

        $workspaces = Workspace::query()
            ->withCount('members')
            ->whereIn('id', $roles->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Response::json([
            'data' => $workspaces->map(fn (Workspace $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'slug' => $w->slug,
                'role' => (string) ($roles[$w->id] ?? 'member'),
                'member_count' => $w->members_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
