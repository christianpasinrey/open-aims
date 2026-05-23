<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class WorkspaceUserSearchController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if ($user === null || ! $workspace instanceof Workspace) {
            return response()->json(['data' => []]);
        }

        $membership = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->first();
        if ($membership === null || ! in_array($membership->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException('Only owners or admins can search users.');
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $memberIds = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('user_id');

        $users = User::query()
            ->whereNotIn('id', $memberIds)
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        $pendingEmails = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->pluck('email')
            ->map(fn (string $e): string => strtolower($e))
            ->flip();

        return response()->json([
            'data' => $users->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'invited' => $pendingEmails->has(strtolower((string) $u->email)),
            ])->all(),
        ]);
    }
}
