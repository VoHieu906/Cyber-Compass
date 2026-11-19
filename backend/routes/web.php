<?php

use Illuminate\Support\Facades\Route;

// API-only application: the user interface is the Next.js frontend.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
