<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Staff;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// Every route must also be listed in tests/Feature/RoleMatrixTest.php.

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // A Kasir's home is the till.
    Route::get('/', fn () => Gate::allows('owner') ? view('home') : to_route('pos'))->name('home');

    Route::view('/pos', 'pos')->name('pos');

    Route::middleware('can:owner')->group(function () {
        Route::livewire('/staff', Staff::class)->name('staff');
    });
});
