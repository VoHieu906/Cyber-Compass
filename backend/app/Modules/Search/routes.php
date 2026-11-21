<?php

use App\Modules\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Open to guests (the brief lists Search in the main navigation); its own limiter, as the header searches while typing.
Route::get('search', SearchController::class)->middleware('throttle:search')->name('search');
