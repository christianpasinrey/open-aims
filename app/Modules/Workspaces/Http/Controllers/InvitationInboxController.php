<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InvitationInboxController
{
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['data' => []]);
        }

        $rows = WorkspaceInvitation::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->with(['workspace:id,name,slug', 'invitedBy:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkspaceInvitation $i): array => [
                'id' => $i->id,
                'role' => $i->role,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'workspace' => $i->workspace ? ['name' => $i->workspace->name, 'slug' => $i->workspace->slug] : null,
                'invited_by' => $i->invitedBy ? ['name' => $i->invitedBy->name] : null,
            ])->all();

        return response()->json(['data' => $rows]);
    }

    public function accept(Request $request, int $id): RedirectResponse
    {
        $invitation = $this->authorizedInvitation($request, $id);

        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $invitation->workspace_id, 'user_id' => $request->user()->getKey()],
            ['role' => $invitation->role, 'joined_at' => now()],
        );
        $invitation->forceFill(['accepted_at' => now()])->save();

        return back();
    }

    public function decline(Request $request, int $id): RedirectResponse
    {
        $invitation = $this->authorizedInvitation($request, $id);
        $invitation->forceFill(['declined_at' => now()])->save();

        return back();
    }

    private function authorizedInvitation(Request $request, int $id): WorkspaceInvitation
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $invitation = WorkspaceInvitation::query()->find($id);
        if ($invitation === null || ! $invitation->isAcceptable()) {
            throw new NotFoundHttpException('Invitation not available.');
        }

        if (strtolower((string) $invitation->email) !== strtolower((string) $user->email)) {
            throw new AccessDeniedHttpException('This invitation is not addressed to you.');
        }

        return $invitation;
    }
}
