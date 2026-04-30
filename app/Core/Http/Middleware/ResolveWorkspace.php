<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves the active Workspace for the current request and binds it
 * as `current.workspace` in the container so global scopes apply.
 *
 * Strategy:
 *   1. {workspace} route parameter (slug)
 *   2. session('current_workspace_id')
 *   3. user's first membership (auto-pick)
 */
final class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $workspace = $this->resolve($request, (int) $user->getKey());

        if ($workspace === null) {
            return $next($request);
        }

        $isMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $isMember) {
            throw new AccessDeniedHttpException('You are not a member of this workspace.');
        }

        app()->instance('current.workspace', $workspace);
        $request->session()->put('current_workspace_id', $workspace->getKey());

        return $next($request);
    }

    private function resolve(Request $request, int $userId): ?Workspace
    {
        $slug = $request->route('workspace');
        if (is_string($slug) && $slug !== '') {
            $byRoute = Workspace::query()->where('slug', $slug)->first();
            if ($byRoute !== null) {
                return $byRoute;
            }
        }

        $sessionId = $request->session()->get('current_workspace_id');
        if (is_int($sessionId) || (is_string($sessionId) && ctype_digit($sessionId))) {
            $bySession = Workspace::query()->find((int) $sessionId);
            if ($bySession !== null) {
                return $bySession;
            }
        }

        $memberRow = WorkspaceMember::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first();

        if ($memberRow === null) {
            return null;
        }

        return Workspace::query()->find($memberRow->workspace_id);
    }
}
