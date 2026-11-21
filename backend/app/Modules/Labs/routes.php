<?php

use App\Modules\Labs\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->controller(LabController::class)->group(function () {
    Route::get('labs', 'index')->name('labs.index');
    Route::get('labs/{slug}', 'show')->name('labs.show');
    Route::post('labs/{slug}/attempts', 'start')->name('labs.start');
    Route::post('lab-attempts/{id}/actions', 'act')->whereNumber('id')->middleware('throttle:lab-actions')->name('lab-attempts.act');
    Route::post('lab-attempts/{id}/reset', 'reset')->whereNumber('id')->middleware('throttle:lab-actions')->name('lab-attempts.reset');
});
