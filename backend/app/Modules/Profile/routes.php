<?php

use App\Modules\Profile\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('me')->name('me.')->controller(ProfileController::class)->group(function () {
    Route::patch('profile', 'update')->name('profile.update');
    Route::post('avatar', 'storeAvatar')->middleware('throttle:uploads')->name('avatar.store');
    Route::delete('avatar', 'destroyAvatar')->name('avatar.destroy');
    Route::patch('preferences', 'updatePreferences')->name('preferences.update');
    Route::put('password', 'updatePassword')->middleware('throttle:password')->name('password.update');
});
