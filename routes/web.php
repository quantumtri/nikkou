<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Sales\HomeController;
use App\Http\Controllers\UnitFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect(LoginController::homeFor(request()->user())))->name('home');

    // Private files: signed, short-lived URLs plus a policy check.
    Route::middleware('signed')->group(function () {
        Route::get('/files/unit-documents/{document}', [UnitFileController::class, 'document'])->name('files.unit-document');
        Route::get('/files/unit-photos/{photo}', [UnitFileController::class, 'photo'])->name('files.unit-photo');
    });

    // Sales mobile app. Admins may use it too (spec section 2).
    Route::prefix('sales')->name('sales.')->middleware('role:admin,sales')->group(function () {
        Route::get('/', HomeController::class)->name('home');
    });
});
