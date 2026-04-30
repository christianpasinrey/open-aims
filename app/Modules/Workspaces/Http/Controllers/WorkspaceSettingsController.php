<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Workspaces\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the General settings page for the active workspace.
 * Mirrors the look-and-feel of `settings/Profile`.
 */
final class WorkspaceSettingsController
{
    public function index(): Response
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            return Inertia::render('workspace/Settings', [
                'ws' => null,
            ]);
        }

        return Inertia::render('workspace/Settings', [
            'ws' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'logo_url' => $workspace->logo_url,
                'color' => HandleInertiaRequests::workspaceColor($workspace),
            ],
        ]);
    }
}
