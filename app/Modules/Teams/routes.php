<?php

declare(strict_types=1);

use App\Modules\Teams\Http\Controllers\LabelPreviewController;
use App\Modules\Teams\Http\Controllers\LabelWriteController;
use App\Modules\Teams\Http\Controllers\TeamMemberListController;
use App\Modules\Teams\Http\Controllers\TeamSettingsController;
use App\Modules\Teams\Http\Controllers\TeamWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
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

    Route::get('teams/{key}/settings', [TeamSettingsController::class, 'index'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.settings');

    Route::patch('teams/{key}', [TeamWriteController::class, 'update'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.update');
});
