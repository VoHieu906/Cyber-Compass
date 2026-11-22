<?php

use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Blog\Http\Controllers\PostAdminController;
use App\Modules\Blog\Http\Controllers\PostController;
use App\Modules\Blog\Http\Controllers\PostRevisionController;
use Illuminate\Support\Facades\Route;

/*
| Blog (F2, ARCHITECTURE D55). Readers (guests too) use slugs. Authoring sits under the
| content admin prefix, so the WAF exclusion for /api/v1/admin/content/ covers large drafts
| (D50); images use the existing content media route.
*/
Route::get('posts', [PostController::class, 'index'])->name('posts.index');
Route::get('posts/{slug}', [PostController::class, 'show'])->name('posts.show');

Route::middleware(['auth:sanctum', EnsureAdmin::class, 'throttle:admin'])->prefix('admin/content/posts')->name('admin.content.posts.')->group(function () {
    Route::controller(PostAdminController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{post:id}', 'show')->whereNumber('post')->name('show');
        Route::put('{post:id}/draft', 'saveDraft')->whereNumber('post')->name('draft.save');
        Route::delete('{post:id}/draft', 'discardDraft')->whereNumber('post')->name('draft.discard');
        Route::post('{post:id}/publish', 'publish')->whereNumber('post')->name('publish');
        Route::post('{post:id}/archive', 'archive')->whereNumber('post')->name('archive');
        Route::post('{post:id}/unarchive', 'unarchive')->whereNumber('post')->name('unarchive');
        Route::delete('{post:id}', 'destroy')->whereNumber('post')->name('destroy');
    });
    Route::get('{post:id}/revisions', [PostRevisionController::class, 'index'])->whereNumber('post')->name('revisions');
    Route::post('{post:id}/revisions/{version}/restore', [PostRevisionController::class, 'restore'])
        ->whereNumber(['post', 'version'])->name('revisions.restore');
});
