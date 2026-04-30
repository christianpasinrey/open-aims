<?php

declare(strict_types=1);

namespace App\Core\Mcp;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves the active workspace for an MCP tool call. Tools should call
 * `$this->bindWorkspace()` at the top of `handle()` so the global
 * `BelongsToWorkspace` scope automatically constrains every Eloquent
 * query to the user's workspace and they cannot accidentally read or
 * write across workspaces.
 *
 * Resolution order:
 *   1. `?workspace_slug=` on the request (explicit override)
 *   2. The user's first WorkspaceMember row by id
 */
trait ResolvesWorkspace
{
    protected function bindWorkspace(?string $slug = null): ?Workspace
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $workspace = $this->resolveWorkspace($user, $slug);
        if ($workspace !== null) {
            app()->instance('current.workspace', $workspace);
        }

        return $workspace;
    }

    private function resolveWorkspace(Authenticatable $user, ?string $slug): ?Workspace
    {
        if ($slug !== null && $slug !== '') {
            $byRoute = Workspace::query()->where('slug', $slug)->first();
            if ($byRoute !== null) {
                $isMember = WorkspaceMember::query()
                    ->where('workspace_id', $byRoute->id)
                    ->where('user_id', $user->getAuthIdentifier())
                    ->exists();
                if ($isMember) {
                    return $byRoute;
                }
            }
        }

        $row = WorkspaceMember::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderBy('id')
            ->first();
        if ($row === null) {
            return null;
        }

        return Workspace::query()->find($row->workspace_id);
    }
}
