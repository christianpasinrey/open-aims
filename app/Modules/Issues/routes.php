<?php

declare(strict_types=1);

use App\Modules\Issues\Http\Controllers\IssueListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('issues', [IssueListController::class, 'index'])->name('issues.index');
});
