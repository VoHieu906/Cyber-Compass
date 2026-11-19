<?php

use App\Modules\Careers\Http\Controllers\CareerController;
use Illuminate\Support\Facades\Route;

Route::get('careers', [CareerController::class, 'index'])->name('careers.index');
Route::get('careers/{slug}', [CareerController::class, 'show'])->name('careers.show');
