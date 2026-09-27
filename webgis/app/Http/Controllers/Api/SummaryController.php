<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErosionHotspot;
use App\Models\ReferenceLandslideEvent;
use App\Models\Shoreline;
use App\Models\ShorelineChangeStat;
use Illuminate\Http\JsonResponse;

class SummaryController extends Controller
{
    private const CLASSES = ['accretion', 'stable', 'mild_erosion', 'severe_erosion'];

    /**
     * GET /api/summary — số liệu tổng quan kiểu "key findings" (DEA Coastlines): phân bố 4 mức biến động,
     * tốc độ trung bình, transect lùi/bồi mạnh nhất. Chỉ đếm/tổng hợp chỉ số pipeline đã tính sẵn.
     */
    public function index(): JsonResponse
    {
        $byClass = ShorelineChangeStat::query()
            ->selectRaw('change_class, COUNT(*) AS n')
            ->groupBy('change_class')
            ->pluck('n', 'change_class');

        $agg = ShorelineChangeStat::query()
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN is_significant THEN 1 ELSE 0 END) AS significant,
                         AVG(lrr_m_per_year) AS mean_lrr, AVG(epr_m_per_year) AS mean_epr')
            ->first();

        $extreme = fn (string $dir) => ShorelineChangeStat::query()
            ->join('transects as t', 't.id', '=', 'shoreline_change_stats.transect_id')
            ->whereNotNull('lrr_m_per_year')
            ->orderBy('lrr_m_per_year', $dir)
            ->first(['t.id', 't.transect_code', 'lrr_m_per_year']);

        $years = Shoreline::query()->distinct()->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();

        $hotspots = ErosionHotspot::query()
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(ST_Length(geom::geography)), 0) AS length_m')
            ->first();

        $reference = ReferenceLandslideEvent::query()
            ->selectRaw("COALESCE(danger_level, 'chua_phan_loai') AS level, COUNT(*) AS n")
            ->groupBy('level')
            ->orderBy('level')
            ->pluck('n', 'level');

        $total = (int) $agg->total;

        return response()->json([
            'transects' => [
                'total' => $total,
                'significant' => (int) $agg->significant,
                'by_class' => collect(self::CLASSES)->mapWithKeys(fn ($c) => [$c => (int) ($byClass[$c] ?? 0)]),
                'mean_lrr_m_per_year' => $this->round($agg->mean_lrr),
                'mean_epr_m_per_year' => $this->round($agg->mean_epr),
                'max_erosion' => $this->transect($extreme('asc')),
                'max_accretion' => $this->transect($extreme('desc')),
            ],
            'shorelines' => ['years' => $years, 'count' => count($years)],
            'hotspots' => ['count' => (int) $hotspots->n, 'length_m' => round((float) $hotspots->length_m)],
            'reference_events' => ['count' => (int) $reference->sum(), 'by_level' => (object) $reference->map(fn ($n) => (int) $n)->all()],
        ]);
    }

    private function round(mixed $v): ?float
    {
        return $v === null ? null : round((float) $v, 2);
    }

    private function transect(?ShorelineChangeStat $row): ?array
    {
        return $row ? ['id' => (int) $row->id, 'transect_code' => $row->transect_code, 'lrr_m_per_year' => (float) $row->lrr_m_per_year] : null;
    }
}
