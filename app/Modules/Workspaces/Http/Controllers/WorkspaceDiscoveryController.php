<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceJoinRequest;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceJoinRequestNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceDiscoveryController
{
    public function join(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $workspace = Workspace::query()->where('slug', $slug)->first();
        if ($workspace === null) {
            throw new NotFoundHttpException('Workspace not found.');
        }

        $alreadyMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->exists();
        if ($alreadyMember) {
            $request->session()->put('current_workspace_id', $workspace->id);

            return redirect()->route('issues.index');
        }

        $policy = (string) $workspace->join_policy;

        if ($policy === 'private') {
            throw new AccessDeniedHttpException('This workspace is private.');
        }

        if ($policy === 'open') {
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->getKey(),
                'role' => 'member',
                'joined_at' => now(),
            ]);
            $request->session()->put('current_workspace_id', $workspace->id);

            return redirect()->route('issues.index');
        }

        WorkspaceJoinRequest::updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->getKey()],
            ['status' => 'pending', 'responded_by_user_id' => null, 'responded_at' => null],
        );

        $admins = User::query()
            ->whereIn('id', WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('role', ['owner', 'admin'])
                ->pluck('user_id'))
            ->get();
        Notification::send($admins, new WorkspaceJoinRequestNotification($workspace, (string) $user->name));

        return back();
    }

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $workspaces = Workspace::query()
            ->whereIn('join_policy', ['open', 'request'])
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'join_policy']);

        $memberIds = WorkspaceMember::query()->where('user_id', $user->getKey())->pluck('workspace_id')->flip();
        $pendingIds = WorkspaceJoinRequest::query()->where('user_id', $user->getKey())->where('status', 'pending')->pluck('workspace_id')->flip();

        $data = $workspaces->map(function (Workspace $w) use ($memberIds, $pendingIds): array {
            $relationship = match (true) {
                $memberIds->has($w->id) => 'member',
                $pendingIds->has($w->id) => 'pending',
                $w->join_policy === 'open' => 'open',
                default => 'request',
            };

            return ['name' => $w->name, 'slug' => $w->slug, 'relationship' => $relationship];
        })->all();

        return response()->json(['data' => $data]);
    }
}
