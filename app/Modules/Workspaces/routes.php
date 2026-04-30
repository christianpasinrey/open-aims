<?php

declare(strict_types=1);

use App\Modules\Workspaces\Http\Controllers\WorkspaceMembersPageController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceSettingsController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    // Members — single endpoint that returns JSON when `?json=1` or
    // `Accept: application/json`, Inertia page otherwise. The JSON shape
    // is consumed by the assignee picker in the issue right rail.
    Route::get('workspace/members', [WorkspaceMembersPageController::class, 'index'])
        ->name('workspace.members');

    // General settings page (Inertia).
    Route::get('workspace/settings', [WorkspaceSettingsController::class, 'index'])
        ->name('workspace.settings');

    // Switch the session's active workspace.
    Route::post('workspace/switch', [WorkspaceWriteController::class, 'switch'])
        ->name('workspace.switch');

    // Update workspace name/color.
    Route::patch('workspace/{slug}', [WorkspaceWriteController::class, 'update'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('workspace.update');
});
