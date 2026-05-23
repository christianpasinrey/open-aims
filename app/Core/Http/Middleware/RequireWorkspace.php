<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends authenticated, verified users who belong to no workspace to the
 * onboarding page, instead of letting them hit "No active workspace" 404s.
 * Runs after ResolveWorkspace (which binds current.workspace when the user
 * is a member). An allowlist avoids redirect loops.
 */
final class RequireWorkspace
{
    /** @var list<string> */
    private const ALLOWED_ROUTE_NAMES = [
        'onboarding', 'workspaces.store', 'workspace.switch',
        'invitations.accept.show', 'invitations.accept', 'logout',
        'dashboard',
    ];

    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'email', 'user/confirm-password', 'two-factor-challenge',
        'invite', 'onboarding', 'workspaces', 'gh', 'build', 'storage',
        'settings',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }
        if (app()->bound('current.workspace')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($request->is($prefix) || $request->is($prefix.'/*')) {
                return $next($request);
            }
        }
        if ($request->expectsJson()) {
            return $next($request);
        }

        return redirect()->route('onboarding');
    }
}
