<?php

use App\Modules\Learning\Http\Controllers\ExerciseController;
use App\Modules\Learning\Http\Controllers\LessonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('lessons/{slug}')->name('lessons.')->controller(LessonController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('start', 'start')->name('start');
    Route::post('complete', 'complete')->name('complete');
});

Route::middleware('auth:sanctum')->controller(ExerciseController::class)->group(function () {
    Route::get('lessons/{slug}/exercise', 'show')->name('exercises.show');
    Route::post('lessons/{slug}/exercise/attempts', 'submit')->middleware('throttle:exercise-attempts')->name('exercises.submit');
    Route::get('exercise-attempts/{id}', 'attempt')->whereNumber('id')->name('exercise-attempts.show');
});
