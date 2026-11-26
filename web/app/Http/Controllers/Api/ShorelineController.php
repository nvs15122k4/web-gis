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
     * ?source=main (mặc định) | s2c1 | landsat — mặc định chỉ trả bản chính để hành vi cũ không đổi.
     */
    public function index(Request $request): JsonResponse
    {
        $years = array_filter((array) $request->query('year'), 'is_numeric');

        $source = $request->query('source', Shoreline::MAIN);
        if (! is_string($source) || ($source !== Shoreline::MAIN && ! isset(Shoreline::ALT_SOURCES[$source]))) {
            return response()->json([
                'message' => 'Nguồn dữ liệu không hợp lệ.',
                'allowed' => [Shoreline::MAIN, ...array_keys(Shoreline::ALT_SOURCES)],
            ], 422);
        }

        $shorelines = Shoreline::withGeoJson()
            ->when($source === Shoreline::MAIN, fn ($q) => $q->main(), fn ($q) => $q->where('shorelines.source', $source))
            ->when($years, fn ($q) => $q->whereIn('year', $years))
            ->orderBy('year')
            ->get();

        $collection = Shoreline::toFeatureCollection($shorelines);
        // Bản chính giữ nguyên hợp đồng JSON cũ (không có thuộc tính `source`); chỉ bản đối chứng mới ghi rõ nguồn.
        if ($source === Shoreline::MAIN) {
            $collection['features'] = array_map(function ($f) {
                unset($f['properties']['source']);

                return $f;
            }, $collection['features']);
        }

        return response()->json($collection);
    }
}
