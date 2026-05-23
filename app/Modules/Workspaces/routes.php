<?php

declare(strict_types=1);

use App\Modules\Workspaces\Http\Controllers\InvitationAcceptController;
use App\Modules\Workspaces\Http\Controllers\InvitationWriteController;
use App\Modules\Workspaces\Http\Controllers\JoinRequestController;
use App\Modules\Workspaces\Http\Controllers\OnboardingController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceDiscoveryController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceMembersPageController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceSettingsController;
use App\Modules\Workspaces\Http\Controllers\WorkspaceWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:10,1'])->group(function (): void {
    Route::get('invite/{token}', [InvitationAcceptController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('invitations.accept.show');
    Route::post('invite/{token}', [InvitationAcceptController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('invitations.accept');
});

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');

    // Members — single endpoint that returns JSON when `?json=1` or
    // `Accept: application/json`, Inertia page otherwise. The JSON shape
    // is consumed by the assignee picker in the issue right rail.
    Route::get('workspace/members', [WorkspaceMembersPageController::class, 'index'])
        ->name('workspace.members');

    // General settings page (Inertia).
    Route::get('workspace/settings', [WorkspaceSettingsController::class, 'index'])
        ->name('workspace.settings');

    // Create a new workspace.
    Route::post('workspaces', [WorkspaceWriteController::class, 'store'])
        ->name('workspaces.store');

    // Switch the session's active workspace.
    Route::post('workspace/switch', [WorkspaceWriteController::class, 'switch'])
        ->name('workspace.switch');

    // Update workspace name/color.
    Route::patch('workspace/{slug}', [WorkspaceWriteController::class, 'update'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('workspace.update');

    // Send a workspace invitation.
    Route::post('workspace/invitations', [InvitationWriteController::class, 'store'])
        ->name('workspace.invitations.store');

    // Workspace discovery search.
    Route::get('workspaces/search', [WorkspaceDiscoveryController::class, 'search'])->name('workspaces.search');

    // Join a workspace (open/request/private).
    Route::post('workspaces/{slug}/join', [WorkspaceDiscoveryController::class, 'join'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('workspaces.join');

    // Join request decisions + pending list.
    Route::get('workspace/requests', [JoinRequestController::class, 'index'])->name('workspace.requests.index');
    Route::post('workspace/requests/{id}/approve', [JoinRequestController::class, 'approve'])->whereNumber('id')->name('workspace.requests.approve');
    Route::post('workspace/requests/{id}/reject', [JoinRequestController::class, 'reject'])->whereNumber('id')->name('workspace.requests.reject');
});
