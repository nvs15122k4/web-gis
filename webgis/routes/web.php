<?php

use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index']);
Route::get('/gioi-thieu', [MapController::class, 'about']);
Route::get('/admin', [MapController::class, 'admin']);
