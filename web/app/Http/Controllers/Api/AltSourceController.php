<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shoreline;
use App\Models\Transect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Hai bản dữ liệu đối chứng độc lập (Sentinel-2 Collection 1, Landsat 8/9) đặt cạnh bản chính để kiểm tra chéo.
 * Chúng KHÔNG thay thế bản chính và không dùng để suy ra dự báo sạt lở.
 */
class AltSourceController extends Controller
{
    private const NOTE = 'Bản đối chứng độc lập, dùng để kiểm tra chéo bản chính; không thay thế bản chính và không phải dự báo sạt lở.';

    /** GET /api/alt-sources — nguồn nào có đường bờ năm nào, năm nào độ tin cậy thấp. */
    public function index(): JsonResponse
    {
        $pg = DB::connection('pgsql');
        $years = $pg->table('shorelines')->select('source', 'year')->distinct()->orderBy('year')->get()->groupBy('source');
        $meta = $pg->table('alt_source_years')->orderBy('year')->get()->groupBy('source');

        $sources = [];
        foreach (Shoreline::ALT_SOURCES as $key => $def) {
            $rows = $meta->get($key, collect());
            $sources[] = [
                'key' => $key,
                'label' => $def['label'],
                'color' => $def['color'],
                'years' => $years->get($key, collect())->pluck('year')->map(fn ($y) => (int) $y)->values()->all(),
                'low_confidence_years' => $rows->where('low_confidence', true)->pluck('year')->map(fn ($y) => (int) $y)->values()->all(),
                'scenes_used' => $rows->mapWithKeys(fn ($r) => [(string) $r->year => $r->scenes_used === null ? null : (int) $r->scenes_used])->all(),
            ];
        }

        return response()->json([
            'main' => [
                'key' => Shoreline::MAIN,
                'label' => 'Bản chính',
                'color' => Shoreline::MAIN_COLOR,
                'years' => $years->get(Shoreline::MAIN, collect())->pluck('year')->map(fn ($y) => (int) $y)->values()->all(),
            ],
            'sources' => $sources,
            'note' => self::NOTE,
        ]);
    }

    /** GET /api/transects/{id}/compare — LRR của transect theo ba nguồn (bản chính lấy từ shoreline_change_stats). */
    public function compare(int $id): JsonResponse
    {
        $transect = Transect::with('stats')->findOrFail($id);
        $pg = DB::connection('pgsql');
        $alt = $pg->table('transect_alt_lrr')->where('transect_id', $id)->get()->keyBy('source');
        $low = $pg->table('alt_source_years')->where('low_confidence', true)->orderBy('year')->get()->groupBy('source');
        $num = fn ($v) => $v === null ? null : (float) $v;

        $sources = [];
        foreach (Shoreline::ALT_SOURCES as $key => $def) {
            $row = $alt->get($key);
            $sources[] = [
                'key' => $key,
                'label' => $def['label'],
                'color' => $def['color'],
                'lrr_m_per_year' => $num($row->lrr_m_per_year ?? null),
                'change_class' => $row->change_class ?? null,
                'diff_vs_main' => $num($row->diff_vs_main ?? null),
                'low_confidence_years' => $low->get($key, collect())->pluck('year')->map(fn ($y) => (int) $y)->values()->all(),
            ];
        }

        return response()->json([
            'id' => $transect->id,
            'transect_code' => $transect->transect_code,
            'main' => [
                'key' => Shoreline::MAIN,
                'label' => 'Bản chính',
                'color' => Shoreline::MAIN_COLOR,
                'lrr_m_per_year' => $num($transect->stats->lrr_m_per_year ?? null),
                'change_class' => $transect->stats->change_class ?? null,
            ],
            'sources' => $sources,
            'note' => self::NOTE,
        ]);
    }
}
