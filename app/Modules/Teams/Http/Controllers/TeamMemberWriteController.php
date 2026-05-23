<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamMember;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TeamMemberWriteController
{
    public function store(Request $request, string $key): RedirectResponse
    {
        [$workspace, $team] = $this->authorize($request, $key);

        $data = $request->validate([
            'user_id' => 'required|integer',
            'role' => 'required|in:lead,member',
        ]);

        $isWorkspaceMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $data['user_id'])
            ->exists();
        if (! $isWorkspaceMember) {
            throw ValidationException::withMessages([
                'user_id' => 'That user is not a member of this workspace.',
            ]);
        }

        TeamMember::query()->firstOrCreate(
            ['team_id' => $team->id, 'user_id' => $data['user_id']],
            ['role' => $data['role']],
        );

        return back();
    }

    public function destroy(Request $request, string $key, int $userId): RedirectResponse
    {
        [, $team] = $this->authorize($request, $key);

        TeamMember::query()
            ->where('team_id', $team->id)
            ->where('user_id', $userId)
            ->delete();

        return back();
    }

    /**
     * @return array{0: Workspace, 1: Team}
     */
    private function authorize(Request $request, string $key): array
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $user = $request->user();
        $membership = $user === null ? null : WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->first();
        if ($membership === null || ! in_array($membership->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException('Only owners or admins can manage team members.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($key))
            ->first();
        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        return [$workspace, $team];
    }
}
