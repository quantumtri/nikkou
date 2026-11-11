<?php

use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\Sales\HomeController;
use App\Http\Controllers\Sales\LoanController;
use App\Http\Controllers\Sales\ReturnController;
use App\Http\Controllers\UnitFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Set-password links (invitations and resets). Signed, single use, and only the latest link works.
Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('/invite/{user}', [InviteController::class, 'show'])->name('invite.show');
    Route::post('/invite/{user}', [InviteController::class, 'store'])->name('invite.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect(LoginController::homeFor(request()->user())))->name('home');

    // Private files: signed, short-lived URLs plus a policy check.
    Route::middleware('signed')->group(function () {
        Route::get('/files/unit-documents/{document}', [UnitFileController::class, 'document'])->name('files.unit-document');
        Route::get('/files/unit-photos/{photo}', [UnitFileController::class, 'photo'])->name('files.unit-photo');
    });

    // Label output (policy-checked in the controller: admin and warehouse).
    Route::get('/admin/labels/pdf', [LabelController::class, 'pdf'])->name('labels.pdf');
    Route::get('/admin/units/{unit}/qr.png', [LabelController::class, 'qr'])->name('labels.qr');

    // Returns and unit lookup are shared by sales, admin and warehouse staff (spec section 2).
    Route::prefix('sales')->name('sales.')->middleware('role:admin,sales,warehouse')->group(function () {
        Route::get('/units/lookup', [LoanController::class, 'lookup'])->middleware('throttle:60,1')->name('units.lookup');
        Route::get('/returns/create', [ReturnController::class, 'create'])->name('returns.create');
        Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store');
    });

    // Sales mobile app. Admins may use it too (spec section 2).
    Route::prefix('sales')->name('sales.')->middleware('role:admin,sales')->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    });
});
