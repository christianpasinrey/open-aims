<?php

declare(strict_types=1);

use App\Modules\Projects\Http\Controllers\ProjectDetailController;
use App\Modules\Projects\Http\Controllers\ProjectListController;
use App\Modules\Projects\Http\Controllers\ProjectPreviewController;
use App\Modules\Projects\Http\Controllers\ProjectResourceController;
use App\Modules\Projects\Http\Controllers\ProjectWriteController;
use App\Modules\Projects\Http\Controllers\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('projects', [ProjectListController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectWriteController::class, 'store'])->name('projects.store');
    Route::get('projects/{slug}/preview', [ProjectPreviewController::class, 'show'])->name('projects.preview');
    Route::get('projects/{slug}', [ProjectDetailController::class, 'show'])->name('projects.show');
    Route::patch('projects/{slug}', [ProjectWriteController::class, 'update'])->name('projects.update');
    Route::delete('projects/{slug}', [ProjectWriteController::class, 'destroy'])->name('projects.destroy');
    Route::post('projects/{slug}/restore', [ProjectWriteController::class, 'restore'])->name('projects.restore');
    Route::delete('projects/{slug}/force', [ProjectWriteController::class, 'forceDestroy'])->name('projects.force-destroy');
    Route::post('projects/{slug}/milestones', [ProjectWriteController::class, 'storeMilestone'])
        ->name('projects.milestones.store');
    Route::post('projects/{slug}/members', [ProjectWriteController::class, 'attachMember'])
        ->name('projects.members.attach');
    Route::delete('projects/{slug}/members/{userId}', [ProjectWriteController::class, 'detachMember'])
        ->whereNumber('userId')
        ->name('projects.members.detach');
    Route::post('projects/{slug}/labels', [ProjectWriteController::class, 'attachLabel'])
        ->name('projects.labels.attach');
    Route::delete('projects/{slug}/labels/{labelId}', [ProjectWriteController::class, 'detachLabel'])
        ->whereNumber('labelId')
        ->name('projects.labels.detach');
    Route::post('projects/{slug}/resources', [ProjectResourceController::class, 'store'])
        ->name('projects.resources.store');
    Route::delete('projects/{slug}/resources/{id}', [ProjectResourceController::class, 'destroy'])
        ->whereNumber('id')
        ->name('projects.resources.destroy');

    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
});
