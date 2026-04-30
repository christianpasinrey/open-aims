<?php

declare(strict_types=1);

use App\Modules\Initiatives\Http\Controllers\InitiativeDetailController;
use App\Modules\Initiatives\Http\Controllers\InitiativeListController;
use App\Modules\Initiatives\Http\Controllers\InitiativeWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('initiatives', [InitiativeListController::class, 'index'])->name('initiatives.index');
    Route::post('initiatives', [InitiativeWriteController::class, 'store'])->name('initiatives.store');
    Route::get('initiatives/{slug}', [InitiativeDetailController::class, 'show'])->name('initiatives.show');
    Route::patch('initiatives/{slug}', [InitiativeWriteController::class, 'update'])->name('initiatives.update');
    Route::post('initiatives/{slug}/projects', [InitiativeWriteController::class, 'attachProject'])
        ->name('initiatives.projects.attach');
    Route::delete('initiatives/{slug}/projects/{project}', [InitiativeWriteController::class, 'detachProject'])
        ->name('initiatives.projects.detach');
});
