<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceJoinDecisionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class JoinRequestController
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
            return response()->json(['data' => []]);
        }

        $rows = WorkspaceJoinRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'pending')
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceJoinRequest $r): array => [
                'id' => $r->id,
                'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email] : null,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all();

        return response()->json(['data' => $rows]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        return $this->decide($request, $id, true);
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->decide($request, $id, false);
    }

    private function decide(Request $request, int $id, bool $approve): RedirectResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            abort(401);
        }

        $jr = WorkspaceJoinRequest::query()->find($id);
        if ($jr === null) {
            throw new NotFoundHttpException('Request not found.');
        }

        $actorMembership = WorkspaceMember::query()
            ->where('workspace_id', $jr->workspace_id)
            ->where('user_id', $actor->getKey())
            ->first();
        if ($actorMembership === null || ! in_array($actorMembership->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException('Only owners or admins can decide join requests.');
        }

        if ($jr->status !== 'pending') {
            return back();
        }

        DB::transaction(function () use ($jr, $actor, $approve): void {
            if ($approve) {
                WorkspaceMember::query()->firstOrCreate(
                    ['workspace_id' => $jr->workspace_id, 'user_id' => $jr->user_id],
                    ['role' => 'member', 'joined_at' => now()],
                );
            }
            $jr->update([
                'status' => $approve ? 'approved' : 'rejected',
                'responded_by_user_id' => $actor->getKey(),
                'responded_at' => now(),
            ]);
        });

        $requester = User::query()->find($jr->user_id);
        $workspace = $jr->workspace;
        if ($requester !== null && $workspace !== null) {
            $requester->notify(new WorkspaceJoinDecisionNotification($workspace, $approve));
        }

        return back();
    }
}
