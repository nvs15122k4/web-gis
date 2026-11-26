<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class YearlyStatsController extends Controller
{
    /**
     * GET /api/yearly-stats — thống kê theo năm, tổng hợp từ khoảng cách baseline → đường bờ của từng transect.
     * Khoảng cách tăng = bờ tiến ra (bồi), giảm = bờ lùi vào (sạt), cùng quy ước với NSM/EPR của pipeline.
     * Chỉ tổng hợp lại dữ liệu đã có; năm không có dữ liệu thì không xuất hiện.
     */
    public function index(): JsonResponse
    {
        $rows = DB::connection('pgsql')->select(<<<'SQL'
            WITH d AS (
                SELECT transect_id, year, distance_m,
                       distance_m - LAG(distance_m) OVER (PARTITION BY transect_id ORDER BY year) AS delta,
                       year - LAG(year) OVER (PARTITION BY transect_id ORDER BY year) AS gap
                FROM transect_shoreline_distances
            )
            SELECT year,
                   COUNT(*) AS n,
                   AVG(distance_m) AS mean_distance_m,
                   AVG(delta) FILTER (WHERE gap = 1) AS mean_delta_m,
                   COUNT(*) FILTER (WHERE gap = 1 AND delta < -2) AS eroding,
                   COUNT(*) FILTER (WHERE gap = 1 AND delta > 2) AS accreting,
                   COUNT(*) FILTER (WHERE gap = 1 AND delta BETWEEN -2 AND 2) AS stable
            FROM d GROUP BY year ORDER BY year
        SQL);

        $first = $rows[0]->mean_distance_m ?? null;

        return response()->json(array_map(fn ($r) => [
            'year' => (int) $r->year,
            'transects' => (int) $r->n,
            'mean_distance_m' => round((float) $r->mean_distance_m, 2),
            'cumulative_m' => $first === null ? null : round((float) $r->mean_distance_m - (float) $first, 2),
            'mean_delta_m' => $r->mean_delta_m === null ? null : round((float) $r->mean_delta_m, 2),
            'eroding' => (int) $r->eroding,
            'accreting' => (int) $r->accreting,
            'stable' => (int) $r->stable,
        ], $rows));
    }
}
