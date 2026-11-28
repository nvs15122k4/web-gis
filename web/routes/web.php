<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index']);
Route::get('/gioi-thieu', [MapController::class, 'about']);
Route::get('/quy-trinh-qgis', [MapController::class, 'workflow']);
Route::get('/huong-dan', [MapController::class, 'guide']);
// Quản trị: khách -> chuyển tới /dang-nhap; không phải admin -> 403.
Route::get('/admin', [MapController::class, 'admin'])->middleware(['auth', 'admin']);

Route::get('/dang-nhap', [SessionController::class, 'create'])->name('login');
Route::post('/dang-nhap', [SessionController::class, 'store']);
Route::post('/dang-xuat', [SessionController::class, 'destroy'])->middleware('auth');

// Báo cáo nội bộ: khách -> chuyển tới /dang-nhap; không phải admin -> 403.
Route::get('/bao-cao', [MapController::class, 'report'])->middleware(['auth', 'admin']);

