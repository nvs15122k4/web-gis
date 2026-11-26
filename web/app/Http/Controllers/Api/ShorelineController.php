<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shoreline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShorelineController extends Controller
{
    /** GET /api/shorelines?year=2020 hoặc ?year[]=2019&year[]=2024 */
    public function index(Request $request): JsonResponse
    {
        $years = array_filter((array) $request->query('year'), 'is_numeric');

        $shorelines = Shoreline::withGeoJson()
            ->when($years, fn ($q) => $q->whereIn('year', $years))
            ->orderBy('year')
            ->get();

        return response()->json(Shoreline::toFeatureCollection($shorelines));
    }
}
