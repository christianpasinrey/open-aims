<?php

declare(strict_types=1);

use App\Modules\Projects\Http\Controllers\ProjectDetailController;
use App\Modules\Projects\Http\Controllers\ProjectListController;
use App\Modules\Projects\Http\Controllers\ProjectWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('projects', [ProjectListController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectWriteController::class, 'store'])->name('projects.store');
    Route::get('projects/{slug}', [ProjectDetailController::class, 'show'])->name('projects.show');
    Route::patch('projects/{slug}', [ProjectWriteController::class, 'update'])->name('projects.update');
    Route::post('projects/{slug}/milestones', [ProjectWriteController::class, 'storeMilestone'])
        ->name('projects.milestones.store');
});
