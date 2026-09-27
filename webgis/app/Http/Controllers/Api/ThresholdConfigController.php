<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ThresholdConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThresholdConfigController extends Controller
{
    /** GET /api/config/thresholds — đọc công khai. */
    public function index(): JsonResponse
    {
        return response()->json(ThresholdConfig::orderBy('key')->get(['key', 'value', 'description']));
    }

    /** PUT /api/admin/config/thresholds/{key} — chỉ admin. */
    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['value' => ['required', 'numeric']]);

        $config = ThresholdConfig::where('key', $key)->firstOrFail();
        $config->update(['value' => $data['value'], 'updated_by' => $request->user()->id]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => "update_threshold:{$key}={$data['value']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json($config->refresh()->only(['key', 'value', 'description']));
    }
}
