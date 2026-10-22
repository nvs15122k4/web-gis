<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferenceLandslideEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceEventController extends Controller
{
    /** GET /api/reference-events?bbox= — luôn trả FeatureCollection hợp lệ, kể cả khi rỗng. */
    public function index(Request $request): JsonResponse
    {
        $bbox = $request->filled('bbox') ? array_map('floatval', explode(',', $request->query('bbox'))) : null;

        $events = ReferenceLandslideEvent::withGeoJson()
            ->when($bbox && count($bbox) === 4, fn ($q) => $q->whereRaw(
                'reference_landslide_events.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $bbox
            ))
            ->orderBy('event_date')
            ->get();

        return response()->json(ReferenceLandslideEvent::toFeatureCollection($events));
    }
}
