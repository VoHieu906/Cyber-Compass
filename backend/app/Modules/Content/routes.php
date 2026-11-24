<?php

use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Content\Http\Controllers\BlockTypeController;
use App\Modules\Content\Http\Controllers\CourseGraphController;
use App\Modules\Content\Http\Controllers\CourseStructureController;
use App\Modules\Content\Http\Controllers\LessonAdminController;
use App\Modules\Content\Http\Controllers\LessonRevisionController;
use App\Modules\Content\Http\Controllers\MediaController;
use App\Modules\Content\Http\Controllers\PackageController;
use App\Modules\Content\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

/*
| Content authoring API (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §5.1), under the admin
| prefix and middleware. Lessons are addressed by id here (learners use slugs).
*/
Route::middleware(['auth:sanctum', EnsureAdmin::class, 'throttle:admin'])->prefix('admin/content')->name('admin.content.')->group(function () {
    Route::get('block-types', BlockTypeController::class)->name('block-types');

    Route::controller(CourseStructureController::class)->group(function () {
        Route::get('courses', 'index')->name('courses');
        Route::post('courses/{course:id}/chapters', 'storeChapter')->whereNumber('course')->name('chapters.store');
        Route::put('courses/{course:id}/chapters/order', 'reorderChapters')->whereNumber('course')->name('chapters.order');
        Route::patch('chapters/{chapter}', 'updateChapter')->whereNumber('chapter')->name('chapters.update');
        Route::put('chapters/{chapter}/lessons/order', 'reorderLessons')->whereNumber('chapter')->name('lessons.order');
    });

    Route::controller(CourseGraphController::class)->prefix('courses/{course:id}/graph')->whereNumber('course')->name('graph.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->name('update');
        Route::post('restore/{entry}', 'restore')->whereNumber('entry')->name('restore');
    });

    Route::controller(LessonAdminController::class)->prefix('lessons')->name('lessons.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{lesson:id}', 'show')->whereNumber('lesson')->name('show');
        Route::put('{lesson:id}/draft', 'saveDraft')->whereNumber('lesson')->name('draft.save');
        Route::delete('{lesson:id}/draft', 'discardDraft')->whereNumber('lesson')->name('draft.discard');
        Route::post('{lesson:id}/publish', 'publish')->whereNumber('lesson')->name('publish');
        Route::post('{lesson:id}/archive', 'archive')->whereNumber('lesson')->name('archive');
        Route::delete('{lesson:id}', 'destroy')->whereNumber('lesson')->name('destroy');
        Route::get('{lesson:id}/revisions', [LessonRevisionController::class, 'index'])->whereNumber('lesson')->name('revisions');
        Route::post('{lesson:id}/revisions/{version}/restore', [LessonRevisionController::class, 'restore'])
            ->whereNumber(['lesson', 'version'])->name('revisions.restore');
    });

    // Content packages (F6, D67, D70). Bodies stay small: package bytes go to S3 by presigned URL in
    // production, and the local part route exists only for a local transfers disk.
    Route::post('packages/check', [PackageController::class, 'check'])->name('packages.check');
    Route::get('lessons/{lesson:id}/package', [PackageController::class, 'lesson'])->whereNumber('lesson')->name('lessons.package');
    Route::get('posts/{post:id}/package', [PackageController::class, 'post'])->whereNumber('post')->name('posts.package');
    Route::controller(TransferController::class)->prefix('transfers')->name('transfers.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('exports', 'export')->name('export');
        Route::get('{transfer}', 'show')->whereNumber('transfer')->name('show');
        Route::put('{transfer}/parts/{part}', 'part')->whereNumber(['transfer', 'part'])->name('part');
        Route::post('{transfer}/complete', 'complete')->whereNumber('transfer')->name('complete');
        Route::post('{transfer}/validate', 'validatePackage')->whereNumber('transfer')->name('validate');
        Route::post('{transfer}/apply', 'apply')->whereNumber('transfer')->name('apply');
        Route::get('{transfer}/download', 'download')->whereNumber('transfer')->name('download');
        Route::get('{transfer}/file', 'file')->whereNumber('transfer')->name('file');
        Route::delete('{transfer}', 'destroy')->whereNumber('transfer')->name('destroy');
    });

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
});
