<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        if (config('services.github.client_id') === null
            || config('services.github.client_id') === '') {
            return redirect('/login')->withErrors([
                'github' => 'GitHub OAuth is not configured. Set GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET in .env.',
            ]);
        }

        $intent = $request->query('intent') === 'connect' ? 'connect' : 'login';
        if ($intent === 'connect' && Auth::guest()) {
            return redirect('/login');
        }
        $request->session()->put('gh_intent', $intent);

        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email', 'read:org'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $intent = $request->session()->pull('gh_intent', 'login');

        // GitHub returns errors as query params before we hit Socialite.
        if ($request->query('error')) {
            $msg = (string) ($request->query('error_description')
                ?: $request->query('error'));

            return $this->redirectAfterFailure($intent, $msg);
        }

        try {
            $ghUser = Socialite::driver('github')->user();
        } catch (Throwable $e) {
            Log::warning('GitHub OAuth callback failed', [
                'intent' => $intent,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectAfterFailure(
                $intent,
                'GitHub authentication failed. Verify GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET and that the registered callback URL matches your APP_URL. (Detail: '.$e->getMessage().')',
            );
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

        // Sign-in only — never auto-create accounts. A workspace owner
        // must invite the user (creating their User row) before they can
        // log in with GitHub.
        $user = User::query()->where('github_id', $githubId)->first();

        if ($user === null && $email !== null) {
            // Existing user with the same email but no GitHub link yet:
            // adopt the link on first sign-in. We do NOT create new users.
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
            return redirect('/login')->withErrors([
                'github' => "No account in this workspace matches GitHub user @{$login}. Ask an admin to invite you first.",
            ]);
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

    private function redirectAfterFailure(string $intent, string $message): RedirectResponse
    {
        $target = $intent === 'connect' ? '/settings/profile' : '/login';

        return redirect($target)->withErrors(['github' => $message]);
    }
}
