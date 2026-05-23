<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Teams\Models\TeamMember;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TeamMemberListController
{
    public function index(string $key): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $team = Team::query()
            ->where('workspace_id', $workspace->id)
            ->where('key', strtoupper($key))
            ->first();

        if ($team === null) {
            throw new NotFoundHttpException('Team not found.');
        }

        $members = TeamMember::query()
            ->where('team_id', $team->id)
            ->with('user:id,name,email')
            ->get();

        $currentRole = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', request()->user()?->getKey())
            ->value('role');

        return Inertia::render('teams/Members', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'color' => $team->color,
            ],
            'currentRole' => $currentRole !== null ? (string) $currentRole : null,
            'members' => $members->map(fn (TeamMember $m): array => [
                'id' => $m->id,
                'role' => $m->role,
                'user' => $m->user ? [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                ] : null,
            ])->filter(static fn ($m) => $m['user'] !== null)->values()->all(),
        ]);
    }
}
