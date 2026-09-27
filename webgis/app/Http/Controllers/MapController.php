<?php

namespace App\Http\Controllers;

use App\Models\Shoreline;
use Illuminate\Contracts\View\View;
use Throwable;

class MapController extends Controller
{
    /** Khoảng năm nghiên cứu theo đề cương; năm nào chưa có composite hợp lệ vẫn hiện nhưng báo "không có dữ liệu". */
    private const STUDY_YEARS = [2018, 2026];

    public function index(): View
    {
        return view('map.index', [
            'geoserver' => config('services.geoserver'),
            'years' => [
                'all' => range(...self::STUDY_YEARS),
                'available' => $this->availableYears(),
            ],
        ]);
    }

    /** Chưa kết nối được PostGIS thì coi như chưa có năm nào (giao diện hiện "đang cập nhật"). */
    private function availableYears(): array
    {
        try {
            return Shoreline::query()->distinct()->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    public function about(): View
    {
        return view('about.index', ['years' => range(...self::STUDY_YEARS)]);
    }

    public function admin(): View
    {
        return view('admin.index');
    }
}
