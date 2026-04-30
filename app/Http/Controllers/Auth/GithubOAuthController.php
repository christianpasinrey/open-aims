<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GitHub OAuth — sign in OR connect a GitHub account to an existing user.
 *
 * Flow
 * - GET /gh/redirect?intent=login    — kick off auth for sign-in
 * - GET /gh/redirect?intent=connect  — kick off auth for linking (auth required)
 * - GET /gh/callback                 — single callback that branches on session intent
 * - DELETE /gh/disconnect            — unlink GitHub from current user
 */
final class GithubOAuthController
{
    public function redirect(Request $request): Response
    {
        $intent = $request->query('intent') === 'connect' ? 'connect' : 'login';
        if ($intent === 'connect' && Auth::guest()) {
            return redirect('/login');
        }
        $request->session()->put('gh_intent', $intent);

        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $intent = $request->session()->pull('gh_intent', 'login');

        try {
            $ghUser = Socialite::driver('github')->user();
        } catch (Throwable $e) {
            return redirect('/login')->withErrors([
                'oauth' => 'GitHub authentication failed: '.$e->getMessage(),
            ]);
        }

        $githubId = (string) $ghUser->getId();
        $email = $ghUser->getEmail() ?? null;
        $name = $ghUser->getName() ?? $ghUser->getNickname() ?? 'GitHub user';
        $avatar = $ghUser->getAvatar();
        $login = $ghUser->getNickname();

        if ($intent === 'connect') {
            $user = $request->user();
            if ($user === null) {
                return redirect('/login');
            }

            $existing = User::query()
                ->where('github_id', $githubId)
                ->where('id', '!=', $user->getKey())
                ->first();
            if ($existing !== null) {
                return redirect('/settings/profile')->withErrors([
                    'github' => 'This GitHub account is already linked to another user.',
                ]);
            }

            $user->forceFill([
                'github_id' => $githubId,
                'github_login' => $login,
                'github_avatar_url' => $avatar,
            ])->save();

            return redirect('/settings/profile')->with('status', 'github-linked');
        }

        // Sign-in / sign-up flow.
        $user = User::query()->where('github_id', $githubId)->first();

        if ($user === null && $email !== null) {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                $user->forceFill([
                    'github_id' => $githubId,
                    'github_login' => $login,
                    'github_avatar_url' => $avatar,
                ])->save();
            }
        }

        if ($user === null) {
            $user = User::create([
                'name' => $name,
                'email' => $email ?? "gh-{$githubId}@users.noreply.github.com",
                'password' => Hash::make(Str::random(40)),
                'github_id' => $githubId,
                'github_login' => $login,
                'github_avatar_url' => $avatar,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/issues');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/login');
        }

        $user->forceFill([
            'github_id' => null,
            'github_login' => null,
            'github_avatar_url' => null,
        ])->save();

        return redirect('/settings/profile')->with('status', 'github-unlinked');
    }
}
