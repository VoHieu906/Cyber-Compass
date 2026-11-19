<?php

use App\Modules\Learning\Http\Controllers\LessonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('lessons/{slug}')->name('lessons.')->controller(LessonController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('start', 'start')->name('start');
    Route::post('complete', 'complete')->name('complete');
});
