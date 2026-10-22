<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/export/{layer}.{format} — tải dữ liệu (như nút "Download" của USGS/DEA).
 * GeoJSON giữ nguyên hình học EPSG:4326; CSV ghi hình học dạng WKT để mở được bằng Excel/QGIS.
 */
class ExportController extends Controller
{
    /** layer => [SQL SELECT (không gồm cột hình học), bảng/alias chứa geom, ORDER BY] */
    private const LAYERS = [
        'transects' => [
            'SELECT t.id, t.transect_code, s.change_class, s.nsm_m, s.epr_m_per_year, s.lrr_m_per_year, s.se_lrr,
                    s.r_squared, s.is_significant, s.consecutive_erosion_periods, s.u_shoreline_avg
             FROM transects t LEFT JOIN shoreline_change_stats s ON s.transect_id = t.id',
            't', 't.transect_code',
        ],
        'shorelines' => ['SELECT l.id, l.year, l.water_index, l.source_image_date FROM shorelines l', 'l', 'l.year'],
        'hotspots' => ['SELECT h.id, h.severity, h.period_start_year, h.period_end_year, h.notes FROM erosion_hotspots h', 'h', 'h.id'],
        'reference' => [
            'SELECT r.id, r.event_date, r.danger_level, r.length_m, r.location, r.description, r.source
             FROM reference_landslide_events r',
            'r', 'r.event_date',
        ],
    ];

    public function show(string $layer, string $format): Response
    {
        [$select, $alias, $order] = self::LAYERS[$layer];
        $geomExpr = $format === 'csv' ? "ST_AsText({$alias}.geom)" : "ST_AsGeoJSON({$alias}.geom, 6)";
        $sql = preg_replace('/\sFROM\s/', ", {$geomExpr} AS geom FROM ", $select, 1)." ORDER BY {$order}";
        $rows = array_map(fn ($r) => (array) $r, DB::connection('pgsql')->select($sql));

        $filename = "webgis_songhau_{$layer}.{$format}";
        $headers = ['Content-Disposition' => "attachment; filename=\"{$filename}\""];

        if ($format === 'csv') {
            return response($this->csv($rows), 200, $headers + ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $features = array_map(fn ($r) => [
            'type' => 'Feature',
            'id' => $r['id'],
            'geometry' => json_decode($r['geom'], true),
            'properties' => array_diff_key($r, ['geom' => true]),
        ], $rows);

        return response(
            json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE),
            200,
            $headers + ['Content-Type' => 'application/geo+json']
        );
    }

    private function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : $v, $row), escape: '');
            }
        }
        rewind($out);

        return stream_get_contents($out);
    }
}
