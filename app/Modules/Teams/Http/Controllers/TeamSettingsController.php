<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Team;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the per-team settings page (general).
 */
final class TeamSettingsController
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

        return Inertia::render('teams/Settings', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'key' => $team->key,
                'icon' => $team->icon,
                'color' => $team->color,
                'description' => $team->description,
                'private' => (bool) $team->private,
            ],
        ]);
    }
}
