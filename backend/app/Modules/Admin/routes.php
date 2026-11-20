<?php

use App\Modules\Admin\Http\Controllers\AdminOverviewController;
use App\Modules\Admin\Http\Controllers\AuditLogController;
use App\Modules\Admin\Http\Controllers\UserAdminController;
use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/*
| Admin console API (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §5.1). Guest 401, learner 403.
| Content admin routes live in the Content module under the same prefix and middleware.
*/
Route::middleware(['auth:sanctum', EnsureAdmin::class, 'throttle:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('overview', AdminOverviewController::class)->name('overview');

    Route::controller(UserAdminController::class)->prefix('users')->name('users.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{user}', 'show')->whereNumber('user')->name('show');
        Route::patch('{user}/role', 'updateRole')->whereNumber('user')->name('role');
        Route::post('{user}/suspend', 'suspend')->whereNumber('user')->name('suspend');
        Route::post('{user}/reactivate', 'reactivate')->whereNumber('user')->name('reactivate');
        Route::post('{user}/password-reset', 'sendPasswordReset')->whereNumber('user')->name('password-reset');
    });

    Route::get('audit-logs', AuditLogController::class)->name('audit-logs');
});
