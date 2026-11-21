<?php

use App\Modules\Roadmap\Http\Controllers\KnowledgeMapController;
use App\Modules\Roadmap\Http\Controllers\LearningPathController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->controller(LearningPathController::class)->group(function () {
    Route::get('learning-path', 'show')->name('learning-path.show');
    Route::post('learning-path/generate', 'generate')->name('learning-path.generate');
    Route::get('learning-paths', 'index')->name('learning-paths.index');
});

Route::middleware('auth:sanctum')->get('knowledge-map', KnowledgeMapController::class)->name('knowledge-map');
