<?php

declare(strict_types=1);

use App\Modules\Issues\Http\Controllers\IssueDetailController;
use App\Modules\Issues\Http\Controllers\IssueListController;
use App\Modules\Issues\Http\Controllers\IssuePreviewController;
use App\Modules\Issues\Http\Controllers\IssueWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('issues', [IssueListController::class, 'index'])->name('issues.index');
    Route::post('issues', [IssueWriteController::class, 'store'])->name('issues.store');
    Route::get('issues/{identifier}/preview', [IssuePreviewController::class, 'show'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.preview');
    Route::get('issues/{identifier}', [IssueDetailController::class, 'show'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.show');
    Route::patch('issues/{identifier}', [IssueWriteController::class, 'update'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.update');
    Route::post('issues/{identifier}/archive', [IssueWriteController::class, 'archive'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.archive');
    Route::post('issues/{identifier}/unarchive', [IssueWriteController::class, 'unarchive'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.unarchive');
    Route::delete('issues/{identifier}', [IssueWriteController::class, 'destroy'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.destroy');
});
