<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET/HEAD /healthz — kiểm tra nhẹ cho UptimeRobot: chỉ `SELECT 1` trên từng kết nối đang dùng.
 * Không đụng phiên, cookie hay bảng dữ liệu.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $names = array_values(array_unique([config('database.default'), 'pgsql']));

        try {
            foreach ($names as $name) {
                DB::connection($name)->select('select 1');
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => 'error'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
