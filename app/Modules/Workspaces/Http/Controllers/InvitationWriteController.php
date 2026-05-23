<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use App\Modules\Workspaces\Notifications\WorkspaceInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InvitationWriteController
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if (! app()->bound('current.workspace')) {
            abort(404, 'No active workspace.');
        }
        /** @var Workspace $workspace */
        $workspace = app('current.workspace');

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->getKey())
            ->first();
        if ($member === null || ! in_array($member->role, ['owner', 'admin'], true)) {
            throw new AccessDeniedHttpException('Only owners or admins can invite.');
        }

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:admin,member,guest',
        ]);

        // lowercase here too: the email mutator runs on save, not on the lookup key
        $invitation = WorkspaceInvitation::updateOrCreate(
            ['workspace_id' => $workspace->id, 'email' => strtolower($data['email'])],
            [
                'role' => $data['role'],
                'token' => Str::random(64),
                'invited_by_user_id' => $user->getKey(),
                'expires_at' => now()->addDays(3),
                'accepted_at' => null,
                'declined_at' => null,
            ],
        );

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation, $workspace->name, $user->name));

        return back();
    }
}
