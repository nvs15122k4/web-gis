<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LayerSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LayerSettingController extends Controller
{
    /** GET /api/config/layers — đọc công khai. */
    public function index(): JsonResponse
    {
        return response()->json(LayerSetting::resolved());
    }

    /** PUT /api/admin/config/layers/{key} — chỉ admin; chỉ sửa trường được gửi lên. */
    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(array_key_exists($key, LayerSetting::DEFAULTS), 404);

        $data = $request->validate([
            'visible' => ['sometimes', 'boolean'],
            'opacity' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $current = collect(LayerSetting::resolved())->firstWhere('key', $key);
        $setting = LayerSetting::updateOrCreate(
            ['key' => $key],
            [
                'visible' => $data['visible'] ?? $current['visible'],
                'opacity' => $data['opacity'] ?? $current['opacity'],
                'sort_order' => $data['sort_order'] ?? $current['sort_order'],
                'updated_by' => $request->user()->id,
            ]
        );

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_layer:'.$key.':'.json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(collect(LayerSetting::resolved())->firstWhere('key', $key));
    }
}
