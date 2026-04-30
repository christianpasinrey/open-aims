<?php

declare(strict_types=1);

use App\Modules\Projects\Http\Controllers\ProjectDetailController;
use App\Modules\Projects\Http\Controllers\ProjectListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('projects', [ProjectListController::class, 'index'])->name('projects.index');
    Route::get('projects/{slug}', [ProjectDetailController::class, 'show'])->name('projects.show');
});
