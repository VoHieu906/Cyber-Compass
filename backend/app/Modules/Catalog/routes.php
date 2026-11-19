<?php

use App\Modules\Catalog\Http\Controllers\CourseController;
use App\Modules\Catalog\Http\Controllers\CourseLearnerController;
use App\Modules\Catalog\Http\Controllers\DomainController;
use Illuminate\Support\Facades\Route;

Route::get('domains', [DomainController::class, 'index'])->name('domains.index');

Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');

Route::middleware('auth:sanctum')->prefix('courses/{slug}')->name('courses.')->controller(CourseLearnerController::class)->group(function () {
    Route::get('progress', 'progress')->name('progress');
    Route::post('enroll', 'enroll')->name('enroll');
    Route::post('favorite', 'favorite')->name('favorite');
    Route::delete('favorite', 'unfavorite')->name('unfavorite');
});
