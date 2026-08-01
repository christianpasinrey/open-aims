<?php

declare(strict_types=1);

namespace App\Core\Mcp;

use App\Models\User;
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
 *   1. `workspace_slug` on the request (explicit choice)
 *   2. The user's first WorkspaceMember row by id (implicit default)
 *
 * An explicit slug that does not resolve to a workspace the user belongs to
 * is an ERROR, never a fallback. Falling back there would silently write to
 * a different workspace than the caller asked for.
 */
trait ResolvesWorkspace
{
    private ?string $workspaceError = null;

    protected function bindWorkspace(?string $slug = null): ?Workspace
    {
        $this->workspaceError = null;

        $user = auth()->user();
        if ($user === null) {
            $this->workspaceError = 'Unauthenticated.';

            return null;
        }

        $workspace = $this->resolveWorkspace($user, $slug);
        if ($workspace !== null) {
            app()->instance('current.workspace', $workspace);
        }

        return $workspace;
    }

    /**
     * Why bindWorkspace() returned null. Always use this for the error
     * response instead of a generic message — it tells the caller which
     * slugs it may actually use.
     */
    protected function workspaceError(): string
    {
        return $this->workspaceError ?? 'No active workspace for this user.';
    }

    /**
     * Resolve a user reference ("me" | numeric id | email) to a user id,
     * restricted to members of the given workspace.
     *
     * Returns null when the reference is empty. Returns false when it points
     * at someone who is not a member — callers must surface that as an error
     * rather than silently leaving the field unset.
     */
    protected function resolveWorkspaceMember(?string $value, Workspace $workspace, int $currentUserId): int|false|null
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        $candidateId = match (true) {
            $value === 'me' => $currentUserId,
            is_numeric($value) => (int) $value,
            default => (int) (User::query()->whereRaw('LOWER(email) = ?', [strtolower($value)])->value('id') ?? 0),
        };

        if ($candidateId <= 0) {
            return false;
        }

        $isMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('user_id', $candidateId)
            ->exists();

        return $isMember ? $candidateId : false;
    }

    /** Slugs the user may pass as workspace_slug, for error messages. */
    protected function availableWorkspaceSlugs(Authenticatable $user): array
    {
        return Workspace::query()
            ->whereIn('id', WorkspaceMember::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->select('workspace_id'))
            ->orderBy('name')
            ->pluck('slug')
            ->all();
    }

    private function resolveWorkspace(Authenticatable $user, ?string $slug): ?Workspace
    {
        if ($slug !== null && $slug !== '') {
            $requested = Workspace::query()->where('slug', $slug)->first();

            if ($requested !== null) {
                $isMember = WorkspaceMember::query()
                    ->where('workspace_id', $requested->getKey())
                    ->where('user_id', $user->getAuthIdentifier())
                    ->exists();

                if ($isMember) {
                    return $requested;
                }
            }

            // Explicit choice that failed. Do NOT fall back — the caller asked
            // for a specific workspace and would otherwise write to another one.
            $available = $this->availableWorkspaceSlugs($user);
            $this->workspaceError = sprintf(
                "Workspace '%s' not found or you are not a member. Available: %s.",
                $slug,
                $available === [] ? '(none)' : implode(', ', $available),
            );

            return null;
        }

        $row = WorkspaceMember::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderBy('id')
            ->first();

        if ($row === null) {
            $this->workspaceError = 'You do not belong to any workspace.';

            return null;
        }

        return Workspace::query()->find($row->workspace_id);
    }
}
