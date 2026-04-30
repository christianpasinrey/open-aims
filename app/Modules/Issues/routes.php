<?php

declare(strict_types=1);

use App\Modules\Issues\Http\Controllers\IssueDetailController;
use App\Modules\Issues\Http\Controllers\IssueListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('issues', [IssueListController::class, 'index'])->name('issues.index');
    Route::get('issues/{identifier}', [IssueDetailController::class, 'show'])
        ->where('identifier', '[A-Za-z]+-\d+')
        ->name('issues.show');
});
