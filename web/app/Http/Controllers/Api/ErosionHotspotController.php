<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErosionHotspot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErosionHotspotController extends Controller
{
    /** GET /api/erosion-hotspots?min_rate= — lọc theo tốc độ lùi bờ tối thiểu (m/năm). */
    public function index(Request $request): JsonResponse
    {
        $hotspots = ErosionHotspot::withGeoJson()
            ->when($request->filled('min_rate'), fn ($q) => $q->where('severity', '>=', (float) $request->query('min_rate')))
            ->get();

        return response()->json(ErosionHotspot::toFeatureCollection($hotspots));
    }
}
