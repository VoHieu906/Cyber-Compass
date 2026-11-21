<?php

use App\Modules\Progress\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('progress')->name('progress.')->controller(ProgressController::class)->group(function () {
    Route::get('summary', 'summary')->name('summary');
    Route::get('activity', 'activity')->name('activity');
    Route::get('skills', 'skills')->name('skills');
    Route::get('recent', 'recent')->name('recent');
    Route::get('next', 'next')->name('next');
    Route::get('courses', 'courses')->name('courses');
    Route::get('assessments', 'assessments')->name('assessments');
});
