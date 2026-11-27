<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shoreline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShorelineController extends Controller
{
    /**
     * GET /api/shorelines?year=2020 hoặc ?year[]=2019&year[]=2024
     * Tuỳ chọn ?tolerance=0.0005 (độ, 0 < t ≤ 0.01) giản lược đường để nhẹ hơn khi xem toàn cảnh; mặc định giữ nguyên dữ liệu.
     */
    public function index(Request $request): JsonResponse
    {
        $years = array_filter((array) $request->query('year'), 'is_numeric');

        $t = $request->query('tolerance');
        $tolerance = is_numeric($t) && $t > 0 && $t <= 0.01 ? (float) $t : null;

        $shorelines = Shoreline::withGeoJsonLean('geom', $tolerance)
            ->when($years, fn ($q) => $q->whereIn('year', $years))
            ->orderBy('year')
            ->get();

        return response()->json(Shoreline::toFeatureCollection($shorelines));
    }
}
