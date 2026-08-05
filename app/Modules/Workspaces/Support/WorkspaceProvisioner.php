<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Support;

use App\Core\Registries\ModuleRegistry;
use App\Modules\Teams\Support\TeamProvisioner;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single entry point for creating a workspace, used by both the web form
 * and the `workspaces-create` MCP tool. A workspace is only usable once it
 * has an owner and at least one team with workflow states, so all three are
 * created together in one transaction.
 */
final class WorkspaceProvisioner
{
    public const JOIN_POLICIES = ['open', 'request', 'private'];

    private const DEFAULT_JOIN_POLICY = 'request';

    /** Fallback when the workspaces module declares no limit. */
    private const FALLBACK_LIMIT = 10;

    public function create(
        Authenticatable $owner,
        string $name,
        ?string $joinPolicy = null,
        ?string $teamName = null,
        ?string $teamKey = null,
    ): Workspace {
        $name = trim($name) !== '' ? trim($name) : 'Workspace';
        $policy = in_array($joinPolicy, self::JOIN_POLICIES, true) ? $joinPolicy : self::DEFAULT_JOIN_POLICY;
        $teamName = (is_string($teamName) && trim($teamName) !== '') ? trim($teamName) : $name;

        return DB::transaction(function () use ($owner, $name, $policy, $teamName, $teamKey): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'owner_user_id' => $owner->getAuthIdentifier(),
                'join_policy' => $policy,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->getAuthIdentifier(),
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            // The new workspace becomes the active one so the global
            // WorkspaceScope stamps the team (and anything created after)
            // with the right workspace_id.
            app()->instance('current.workspace', $workspace);

            app(TeamProvisioner::class)->create($workspace, $teamName, $teamKey);

            return $workspace;
        });
    }

    /** How many workspaces this user owns. */
    public function ownedCount(Authenticatable $user): int
    {
        return Workspace::query()
            ->where('owner_user_id', $user->getAuthIdentifier())
            ->count();
    }

    public function limitPerUser(): int
    {
        $limits = app(ModuleRegistry::class)->get('workspaces')?->defaultLimits() ?? [];
        $limit = $limits['max_workspaces_per_user'] ?? self::FALLBACK_LIMIT;

        return is_int($limit) && $limit > 0 ? $limit : self::FALLBACK_LIMIT;
    }

    public function hasReachedLimit(Authenticatable $user): bool
    {
        return $this->ownedCount($user) >= $this->limitPerUser();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'workspace';
        }
        for ($i = 0; $i < 5; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            if (! Workspace::query()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        return $base.'-'.now()->format('YmdHis');
    }
}
