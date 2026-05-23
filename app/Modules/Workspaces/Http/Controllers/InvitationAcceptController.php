<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InvitationAcceptController
{
    use PasswordValidationRules;

    public function show(string $token): InertiaResponse
    {
        $invitation = WorkspaceInvitation::query()->where('token', $token)->first();

        if ($invitation === null || ! $invitation->isAcceptable()) {
            return Inertia::render('auth/AcceptInvitation', ['valid' => false]);
        }

        $accountExists = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->exists();

        return Inertia::render('auth/AcceptInvitation', [
            'valid' => true,
            'token' => $token,
            'email' => $invitation->email,
            'workspaceName' => $invitation->workspace?->name,
            'accountExists' => $accountExists,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = WorkspaceInvitation::query()->where('token', $token)->first();
        if ($invitation === null || ! $invitation->isAcceptable()) {
            return redirect()->route('login')->withErrors(['invitation' => 'Invitación inválida o caducada.']);
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])->first();

        if ($existing === null) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => $this->passwordRules(),
            ]);

            $user = DB::transaction(function () use ($invitation, $data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                ]);
                $this->attachMembership($invitation, (int) $user->getKey());

                return $user;
            });

            Auth::login($user);
            $request->session()->put('current_workspace_id', $invitation->workspace_id);

            return redirect()->route('issues.index');
        }

        if (Auth::id() !== $existing->getKey()) {
            return redirect()->route('login')->withErrors([
                'invitation' => 'Inicia sesión con '.$invitation->email.' para aceptar la invitación.',
            ]);
        }

        $this->attachMembership($invitation, (int) $existing->getKey());
        $request->session()->put('current_workspace_id', $invitation->workspace_id);

        return redirect()->route('issues.index');
    }

    private function attachMembership(WorkspaceInvitation $invitation, int $userId): void
    {
        WorkspaceMember::query()->firstOrCreate(
            ['workspace_id' => $invitation->workspace_id, 'user_id' => $userId],
            ['role' => $invitation->role, 'joined_at' => now()],
        );
        $invitation->forceFill(['accepted_at' => now()])->save();
    }
}
