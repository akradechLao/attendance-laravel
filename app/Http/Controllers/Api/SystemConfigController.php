<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    /**
     * Get all config grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $grouped = SystemConfigService::grouped();
        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Get config for a specific category.
     */
    public function category(string $category): JsonResponse
    {
        $all = SystemConfigService::all();
        $filtered = array_filter($all, fn($item) => $item['category'] === $category);

        if (empty($filtered)) {
            return response()->json(['success' => false, 'message' => 'ไม่พบหมวดหมู่นี้'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $filtered,
        ]);
    }

    /**
     * Update multiple config values at once.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string',
            'configs.*.value' => 'required|string',
        ]);

        $results = [];
        foreach ($request->configs as $item) {
            $success = SystemConfigService::set($item['key'], $item['value']);
            $results[$item['key']] = $success ? 'ok' : 'error';
        }

        SystemConfigService::clearCache();

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => 'อัพเดทค่าตั้งเรียบร้อย',
        ]);
    }

    /**
     * Reset all config to defaults.
     */
    public function reset(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\DB::table('system_config')->whereNull('company_id')->delete();
            SystemConfigService::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'รีเซ็ตค่าตั้งเป็นค่าเริ่มต้นแล้ว',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single config value.
     */
    public function get(string $key): JsonResponse
    {
        $value = SystemConfigService::get($key);
        if ($value === null) {
            return response()->json(['success' => false, 'message' => 'ไม่พบค่านี้'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['key' => $key, 'value' => $value],
        ]);
    }
}
