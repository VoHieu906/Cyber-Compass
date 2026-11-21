<?php

use App\Modules\Auth\Http\Controllers\CurrentUserController;
use App\Modules\Auth\Http\Controllers\PasswordResetController;
use App\Modules\Auth\Http\Controllers\RegistrationController;
use App\Modules\Auth\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', RegistrationController::class)->middleware('throttle:register')->name('register');
    // Per address on top of AttemptLogin's per-account lockout: one address trying many accounts
    // (password spraying) is stopped here (docs/SECURITY_REVIEW.md).
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password')->name('password.email');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password')->name('password.store');

    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum')->name('logout');
});

Route::get('me', CurrentUserController::class)->middleware('auth:sanctum')->name('me');
