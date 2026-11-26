<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RatePointController extends Controller
{
    /**
     * GET /api/rate-points — mỗi transect một điểm đặt đúng vị trí đường bờ của năm mới nhất có dữ liệu
     * (nội suy theo khoảng cách baseline → đường bờ đã lưu), kèm tốc độ biến động; vẽ kiểu DEA Coastlines.
     * Chỉ biến đổi dữ liệu pipeline đã tính, không tính thêm chỉ số mới.
     */
    public function index(): JsonResponse
    {
        $rows = DB::connection('pgsql')->select(<<<'SQL'
            SELECT t.id, t.transect_code, d.year, s.change_class, s.epr_m_per_year, s.lrr_m_per_year, s.nsm_m, s.is_significant,
                   ST_X(p.geom) AS lng, ST_Y(p.geom) AS lat
            FROM transects t
            JOIN shoreline_change_stats s ON s.transect_id = t.id
            JOIN LATERAL (
                SELECT year, distance_m FROM transect_shoreline_distances WHERE transect_id = t.id ORDER BY year DESC LIMIT 1
            ) d ON true
            CROSS JOIN LATERAL (
                SELECT ST_LineInterpolatePoint(t.geom, LEAST(1.0, GREATEST(0.0, d.distance_m / NULLIF(ST_Length(t.geom::geography), 0)))) AS geom
            ) p
            ORDER BY t.transect_code
        SQL);

        $features = array_map(fn ($r) => [
            'type' => 'Feature',
            'id' => (int) $r->id,
            'geometry' => ['type' => 'Point', 'coordinates' => [(float) $r->lng, (float) $r->lat]],
            'properties' => [
                'id' => (int) $r->id,
                'transect_code' => $r->transect_code,
                'year' => (int) $r->year,
                'change_class' => $r->change_class,
                'epr_m_per_year' => $r->epr_m_per_year === null ? null : (float) $r->epr_m_per_year,
                'lrr_m_per_year' => $r->lrr_m_per_year === null ? null : (float) $r->lrr_m_per_year,
                'nsm_m' => $r->nsm_m === null ? null : (float) $r->nsm_m,
                'is_significant' => (bool) $r->is_significant,
            ],
        ], $rows);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
