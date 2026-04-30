<?php

declare(strict_types=1);

use App\Modules\Workspaces\Http\Controllers\WorkspaceMemberListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('workspace/members', [WorkspaceMemberListController::class, 'index'])
        ->name('workspace.members');
});
