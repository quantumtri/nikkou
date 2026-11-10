<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Sales\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect(LoginController::homeFor(request()->user())))->name('home');

    // Sales mobile app. Admins may use it too (spec section 2).
    Route::prefix('sales')->name('sales.')->middleware('role:admin,sales')->group(function () {
        Route::get('/', HomeController::class)->name('home');
    });
});
