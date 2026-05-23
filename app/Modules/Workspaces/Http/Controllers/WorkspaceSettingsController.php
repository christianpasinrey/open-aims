<?php

declare(strict_types=1);

namespace App\Modules\Workspaces\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
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
                'telegram' => [
                    'enabled' => (bool) ($workspace->settings['telegram']['enabled'] ?? false),
                    'chat_id' => $workspace->settings['telegram']['chat_id'] ?? null,
                ],
                'current_role' => WorkspaceMember::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('user_id', request()->user()?->getKey())
                    ->value('role'),
            ],
        ]);
    }
}
