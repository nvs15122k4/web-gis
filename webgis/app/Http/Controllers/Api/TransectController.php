<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransectController extends Controller
{
    /** GET /api/transects?bbox=minLng,minLat,maxLng,maxLat&change_class= */
    public function index(Request $request): JsonResponse
    {
        $bbox = $request->filled('bbox') ? array_map('floatval', explode(',', $request->query('bbox'))) : null;

        $transects = Transect::withGeoJson()
            ->join('shoreline_change_stats as s', 's.transect_id', '=', 'transects.id')
            ->addSelect('s.change_class', 's.epr_m_per_year', 's.lrr_m_per_year')
            ->when($request->filled('change_class'), fn ($q) => $q->where('s.change_class', $request->query('change_class')))
            ->when($bbox && count($bbox) === 4, fn ($q) => $q->whereRaw(
                'transects.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox
            ))
            ->get();

        return response()->json(Transect::toFeatureCollection($transects));
    }

    /** GET /api/transects/{id} */
    public function show(int $id): JsonResponse
    {
        $transect = Transect::with('stats')->findOrFail($id);

        return response()->json([
            'id' => $transect->id,
            'transect_code' => $transect->transect_code,
            'stats' => $transect->stats,
        ]);
    }

    /** GET /api/transects/{id}/history — chuỗi năm → khoảng cách baseline, chỉ các năm có dữ liệu. */
    public function history(int $id): JsonResponse
    {
        $transect = Transect::with('distances')->findOrFail($id);

        return response()->json(
            $transect->distances->map(fn ($d) => ['year' => $d->year, 'distance_m' => $d->distance_m])->values()
        );
    }
}
