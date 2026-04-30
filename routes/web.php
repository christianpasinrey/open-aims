<?php

use App\Modules\Issues\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('issues.index');
    }

    return inertia('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/issues')->name('dashboard');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
});

require __DIR__.'/settings.php';
