<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight JSON list of the current workspace's members.
 * Used by the assignee picker in the issue right rail.
 */
final class WorkspaceMemberListController
{
    public function index(): JsonResponse
    {
        if (! app()->bound('current.workspace')) {
            return response()->json(['data' => []]);
        }
        $workspace = app('current.workspace');
        if (! $workspace instanceof Workspace) {
            return response()->json(['data' => []]);
        }

        $userIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('user_id');

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'data' => $users->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->all(),
        ]);
    }
}
