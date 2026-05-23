<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeamListController
{
    public function index(Request $request): Response|JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;

        if ($this->wantsJson($request)) {
            if (! $workspace instanceof Workspace) {
                return response()->json(['data' => []]);
            }
            $teams = Team::query()
                ->where('workspace_id', $workspace->id)
                ->withCount(['members', 'issues'])
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'color', 'icon']);

            return response()->json([
                'data' => $teams->map(fn (Team $t): array => [
                    'key' => $t->key,
                    'name' => $t->name,
                    'color' => $t->color,
                    'icon' => $t->icon,
                    'issue_count' => (int) $t->issues_count,
                    'member_count' => (int) $t->members_count,
                ])->all(),
            ]);
        }

        $currentUserId = $request->user()?->getKey();
        $currentRole = $workspace instanceof Workspace
            ? WorkspaceMember::query()->where('workspace_id', $workspace->id)->where('user_id', $currentUserId)->value('role')
            : null;

        return Inertia::render('workspace/Teams', [
            'currentRole' => $currentRole !== null ? (string) $currentRole : null,
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->query('json') === '1') {
            return true;
        }

        return $request->wantsJson() && ! $request->header('X-Inertia');
    }
}
