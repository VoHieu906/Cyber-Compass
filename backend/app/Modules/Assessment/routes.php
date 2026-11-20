<?php

use App\Modules\Assessment\Http\Controllers\AssessmentController;
use App\Modules\Assessment\Http\Controllers\AttemptController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('assessments/active', [AssessmentController::class, 'active'])->name('assessments.active');
    Route::post('assessments/{slug}/attempts', [AssessmentController::class, 'start'])->name('assessments.start');

    Route::prefix('assessment-attempts')->name('assessment-attempts.')->controller(AttemptController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{attempt}', 'show')->whereNumber('attempt')->name('show');
        Route::put('{attempt}/answers/{question}', 'answer')->whereNumber(['attempt', 'question'])->name('answer');
        Route::post('{attempt}/submit', 'submit')->whereNumber('attempt')->name('submit');
        Route::get('{attempt}/result', 'result')->whereNumber('attempt')->name('result');
    });
});
