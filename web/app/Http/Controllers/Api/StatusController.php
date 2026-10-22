<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErosionHotspot;
use App\Models\ReferenceLandslideEvent;
use App\Models\Shoreline;
use App\Models\ThresholdConfig;
use App\Models\Transect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class StatusController extends Controller
{
    /**
     * GET /api/status — tình trạng các thành phần và số bản ghi mỗi lớp dữ liệu.
     * Giá trị null = chưa kết nối được / chưa migrate; 0 = đã có bảng nhưng dữ liệu đang cập nhật.
     */
    public function index(): JsonResponse
    {
        $data = [
            'shorelines' => $this->count(fn () => Shoreline::count()),
            'transects' => $this->count(fn () => Transect::count()),
            'hotspots' => $this->count(fn () => ErosionHotspot::count()),
            'reference_events' => $this->count(fn () => ReferenceLandslideEvent::count()),
        ];

        return response()->json([
            'mysql' => $this->count(fn () => ThresholdConfig::count()) !== null,
            'postgis' => $data['shorelines'] !== null,
            'geoserver' => $this->geoserverUp(),
            'data' => $data,
        ]);
    }

    private function count(callable $query): ?int
    {
        try {
            return $query();
        } catch (Throwable) {
            return null;
        }
    }

    private function geoserverUp(): bool
    {
        $gs = config('services.geoserver');
        try {
            return Http::timeout(2)->get("{$gs['url']}/{$gs['workspace']}/wms", [
                'service' => 'WMS', 'request' => 'GetCapabilities',
            ])->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
