<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Members page for the active workspace.
 *
 * This single controller backs `GET /workspace/members` for both:
 *  - the Inertia page (default), used as the full members list view
 *  - a JSON list (when `Accept: application/json` is sent OR when `?json=1`
 *    is on the URL), used by the assignee picker in the issue right rail.
 *
 * Keeping it dual-purpose at one URL avoids breaking existing callers and
 * keeps the public surface tiny.
 */
final class WorkspaceMembersPageController
{
    public function index(Request $request): Response|JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            if ($this->wantsJson($request)) {
                return response()->json(['data' => []]);
            }

            return Inertia::render('workspace/Members', [
                'members' => [],
                'count' => 0,
            ]);
        }

        $members = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get(['id', 'workspace_id', 'user_id', 'role', 'joined_at']);

        if ($this->wantsJson($request)) {
            $userIds = $members->pluck('user_id')->all();
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

        $rows = $members
            ->filter(fn (WorkspaceMember $m) => $m->user !== null)
            ->map(fn (WorkspaceMember $m): array => [
                'id' => $m->id,
                'role' => (string) ($m->role ?? 'member'),
                'joined_at' => $m->joined_at?->toIso8601String(),
                'user' => [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                ],
            ])
            ->values()
            ->all();

        $currentUserId = $request->user()?->getKey();
        $currentRole = $members
            ->first(fn (WorkspaceMember $m) => $m->user_id === $currentUserId)
            ?->role ?? null;

        return Inertia::render('workspace/Members', [
            'members' => $rows,
            'count' => count($rows),
            'currentRole' => $currentRole !== null ? (string) $currentRole : null,
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->query('json') === '1') {
            return true;
        }

        return $request->wantsJson() && ! $request->header('X-Inertia');
    }
}
