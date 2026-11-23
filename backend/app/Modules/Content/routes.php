<?php

use App\Modules\Admin\Http\Middleware\EnsureAdmin;
use App\Modules\Content\Http\Controllers\BlockTypeController;
use App\Modules\Content\Http\Controllers\CourseGraphController;
use App\Modules\Content\Http\Controllers\CourseStructureController;
use App\Modules\Content\Http\Controllers\LessonAdminController;
use App\Modules\Content\Http\Controllers\LessonRevisionController;
use App\Modules\Content\Http\Controllers\MediaController;
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

    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
});
