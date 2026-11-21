<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('unread-count', 'unreadCount')->name('unread-count');
        Route::post('read-all', 'readAll')->name('read-all');
        Route::post('{id}/read', 'read')->whereUuid('id')->name('read');
        Route::delete('{id}', 'destroy')->whereUuid('id')->name('destroy');
    });

    Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('me.notification-preferences.show');
    Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('me.notification-preferences.update');
});
