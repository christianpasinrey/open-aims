<?php

declare(strict_types=1);

use App\Modules\Views\Http\Controllers\IssueViewListController;
use App\Modules\Views\Http\Controllers\IssueViewShowController;
use App\Modules\Views\Http\Controllers\IssueViewWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('views', [IssueViewListController::class, 'index'])->name('views.index');
    Route::post('views', [IssueViewWriteController::class, 'store'])->name('views.store');
    Route::get('views/{view}', [IssueViewShowController::class, 'show'])
        ->whereNumber('view')
        ->name('views.show');
    Route::patch('views/{view}', [IssueViewWriteController::class, 'update'])
        ->whereNumber('view')
        ->name('views.update');
    Route::delete('views/{view}', [IssueViewWriteController::class, 'destroy'])
        ->whereNumber('view')
        ->name('views.destroy');
    Route::post('views/{view}/favorite', [IssueViewWriteController::class, 'favorite'])
        ->whereNumber('view')
        ->name('views.favorite');
});
