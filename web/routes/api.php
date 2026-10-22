<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ErosionHotspotController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ReferenceEventController;
use App\Http\Controllers\Api\ShorelineController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\ThresholdConfigController;
use App\Http\Controllers\Api\TransectController;
use Illuminate\Support\Facades\Route;

Route::get('/status', [StatusController::class, 'index']);
Route::get('/summary', [SummaryController::class, 'index']);
Route::get('/export/{layer}.{format}', [ExportController::class, 'show'])
    ->whereIn('layer', ['transects', 'shorelines', 'hotspots', 'reference'])
    ->whereIn('format', ['geojson', 'csv']);

Route::get('/shorelines', [ShorelineController::class, 'index']);

Route::get('/transects', [TransectController::class, 'index']);
Route::get('/transects/{id}', [TransectController::class, 'show'])->whereNumber('id');
Route::get('/transects/{id}/history', [TransectController::class, 'history'])->whereNumber('id');

Route::get('/erosion-hotspots', [ErosionHotspotController::class, 'index']);
Route::get('/reference-events', [ReferenceEventController::class, 'index']);
Route::get('/config/thresholds', [ThresholdConfigController::class, 'index']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::put('/admin/config/thresholds/{key}', [ThresholdConfigController::class, 'update']);
});
