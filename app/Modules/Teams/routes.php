<?php

declare(strict_types=1);

use App\Modules\Teams\Http\Controllers\LabelPreviewController;
use App\Modules\Teams\Http\Controllers\LabelWriteController;
use App\Modules\Teams\Http\Controllers\TeamListController;
use App\Modules\Teams\Http\Controllers\TeamMemberListController;
use App\Modules\Teams\Http\Controllers\TeamMemberWriteController;
use App\Modules\Teams\Http\Controllers\TeamSettingsController;
use App\Modules\Teams\Http\Controllers\TeamWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::post('teams', [TeamWriteController::class, 'store'])->name('teams.store');

    Route::get('workspace/teams', [TeamListController::class, 'index'])->name('workspace.teams');
    Route::get('labels/{id}/preview', [LabelPreviewController::class, 'show'])
        ->where('id', '\d+')
        ->name('labels.preview');

    Route::get('teams/{key}/labels', [LabelWriteController::class, 'index'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.labels.index');
    Route::post('teams/{key}/labels', [LabelWriteController::class, 'store'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.labels.store');
    Route::patch('labels/{id}', [LabelWriteController::class, 'update'])
        ->where('id', '\d+')
        ->name('labels.update');
    Route::delete('labels/{id}', [LabelWriteController::class, 'destroy'])
        ->where('id', '\d+')
        ->name('labels.destroy');

    Route::get('teams/{key}/members', [TeamMemberListController::class, 'index'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.members');

    Route::post('teams/{key}/members', [TeamMemberWriteController::class, 'store'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.members.store');
    Route::delete('teams/{key}/members/{userId}', [TeamMemberWriteController::class, 'destroy'])
        ->where('key', '[A-Za-z0-9]+')
        ->whereNumber('userId')
        ->name('teams.members.destroy');

    Route::get('teams/{key}/settings', [TeamSettingsController::class, 'index'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.settings');

    Route::patch('teams/{key}', [TeamWriteController::class, 'update'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.update');
});
