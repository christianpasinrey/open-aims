<?php

declare(strict_types=1);

use App\Modules\Teams\Http\Controllers\TeamMemberListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('teams/{key}/members', [TeamMemberListController::class, 'index'])
        ->where('key', '[A-Za-z0-9]+')
        ->name('teams.members');
});
