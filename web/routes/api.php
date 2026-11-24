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

// Tình trạng hệ thống: cache ngắn 30s ở CDN (mỗi lần tính phải đếm 4 bảng PostGIS qua mạng, ~3s trên Vercel).
Route::get('/status', [StatusController::class, 'index'])
    ->middleware('cache.headers:public;max_age=15;s_maxage=30;stale_while_revalidate=60;etag');

// Ngưỡng cấu hình: không cache công khai (admin vừa sửa phải thấy ngay).
Route::get('/config/thresholds', [ThresholdConfigController::class, 'index']);

// Dữ liệu chỉ đổi khi chạy lại pipeline: cho trình duyệt cache 1 phút, CDN (Vercel) 5 phút, kèm ETag để xác thực 304
// và stale-while-revalidate để người dùng không phải chờ truy vấn PostGIS khi cache hết hạn.
Route::middleware('cache.headers:public;max_age=60;s_maxage=300;stale_while_revalidate=600;etag')->group(function () {
    Route::get('/summary', [SummaryController::class, 'index']);
    Route::get('/export/{layer}.{format}', [ExportController::class, 'show'])
        ->whereIn('layer', ['transects', 'shorelines', 'hotspots', 'reference'])
        ->whereIn('format', ['geojson', 'csv']);

    Route::get('/shorelines', [ShorelineController::class, 'index']);

    Route::get('/transects', [TransectController::class, 'index']);
    Route::get('/transects/{id}', [TransectController::class, 'show'])->where('id', '[0-9]{1,9}');
    Route::get('/transects/{id}/history', [TransectController::class, 'history'])->where('id', '[0-9]{1,9}');

    Route::get('/erosion-hotspots', [ErosionHotspotController::class, 'index']);
    Route::get('/reference-events', [ReferenceEventController::class, 'index']);
});

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::put('/admin/config/thresholds/{key}', [ThresholdConfigController::class, 'update']);
});
