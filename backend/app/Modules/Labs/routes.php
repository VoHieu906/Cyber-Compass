<?php

use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Labs\Http\Controllers\LabAdminController;
use App\Modules\Labs\Http\Controllers\LabController;
use App\Modules\Labs\Http\Controllers\LabPreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->controller(LabController::class)->group(function () {
    Route::get('labs', 'index')->name('labs.index');
    Route::get('labs/{slug}', 'show')->name('labs.show');
    Route::post('labs/{slug}/attempts', 'start')->name('labs.start');
    Route::post('lab-attempts/{id}/actions', 'act')->whereNumber('id')->middleware('throttle:lab-actions')->name('lab-attempts.act');
    Route::post('lab-attempts/{id}/reset', 'reset')->whereNumber('id')->middleware('throttle:lab-actions')->name('lab-attempts.reset');
});

/*
| Lab management (F7). Under admin/content so the WAF exclusion for admin content bodies covers
| scenarios that look like shell commands. These routes carry answers: never reuse them for learners.
*/
Route::middleware(['auth:sanctum', EnsureAdmin::class, 'throttle:admin'])->prefix('admin/content/labs')->name('admin.content.labs.')->group(function () {
    Route::controller(LabAdminController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('order', 'order')->name('order');
        Route::get('{lab:id}', 'show')->whereNumber('lab')->name('show');
        Route::put('{lab:id}/draft', 'saveDraft')->whereNumber('lab')->name('draft.save');
        Route::delete('{lab:id}/draft', 'discardDraft')->whereNumber('lab')->name('draft.discard');
        Route::post('{lab:id}/check', 'check')->whereNumber('lab')->name('check');
        Route::post('{lab:id}/publish', 'publish')->whereNumber('lab')->name('publish');
        Route::post('{lab:id}/archive', 'archive')->whereNumber('lab')->name('archive');
        Route::delete('{lab:id}/archive', 'unarchive')->whereNumber('lab')->name('unarchive');
        Route::get('{lab:id}/revisions', 'revisions')->whereNumber('lab')->name('revisions');
        Route::post('{lab:id}/revisions/{version}/restore', 'restore')->whereNumber(['lab', 'version'])->name('revisions.restore');
    });
    Route::controller(LabPreviewController::class)->group(function () {
        Route::post('{lab:id}/preview', 'show')->whereNumber('lab')->name('preview');
        Route::post('{lab:id}/preview/actions', 'act')->whereNumber('lab')->middleware('throttle:lab-actions')->name('preview.act');
        Route::post('{lab:id}/preview/reset', 'reset')->whereNumber('lab')->middleware('throttle:lab-actions')->name('preview.reset');
    });
});
