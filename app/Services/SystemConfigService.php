<?php

namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemConfigService
{
    /**
     * Hardcoded fallback defaults — used when DB is unavailable or config missing.
     * These ensure the system always works even if system_config table is empty/corrupted.
     */
    private const DEFAULTS = [
        // ─── Attendance Policy ───
        'late_grace_minutes' => ['value' => '0', 'type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีที่สายแล้วยังไม่ถูกนับเป็นสาย (grace period)'],
        'late_threshold_minutes' => ['value' => '30', 'type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีที่สายเกินกว่าจะถูกหักลา (เช่น 30 นาที = หักลา 1 ชม.)'],
        'forced_leave_minutes' => ['value' => '60', 'type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีของลาบังคับเมื่อสายเกิน threshold'],
        'max_check_in_rounds' => ['value' => '4', 'type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนรอบสูงสุดที่เช็คอิน/เช็คเอาท์ได้ต่อวัน'],
        'auto_checkout_time' => ['value' => '', 'type' => 'string', 'category' => 'attendance', 'description' => 'เวลาที่ระบบจะ auto-checkout ถ้าพนักงานไม่เช็คเอาท์ (เช่น 18:00). ว่าง = ไม่ auto'],
        'auto_checkout_buffer_minutes' => ['value' => '30', 'type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีหลังเวลาเลิกงานที่ระบบจะรอ ก่อนเติม auto-checkout (เช่น 30 = รอ 30 นาทีหลัง end_time)'],

        // ─── OT Policy ───
        'max_ot_hours_per_day' => ['value' => '4', 'type' => 'integer', 'category' => 'ot', 'description' => 'ชั่วโมง OT สูงสุดต่อวัน'],
        'max_ot_hours_per_week' => ['value' => '36', 'type' => 'integer', 'category' => 'ot', 'description' => 'ชั่วโมง OT สูงสุดต่อสัปดาห์'],
        'ot_minimum_minutes' => ['value' => '60', 'type' => 'integer', 'category' => 'ot', 'description' => 'จำนวนนาทีขั้นต่ำของ OT (เช่น 60 = นับเป็น 1 ชม.)'],
        'ot_requires_approval' => ['value' => 'true', 'type' => 'boolean', 'category' => 'ot', 'description' => 'OT ต้องได้รับอนุมัติก่อนหรือไม่'],

        // ─── WFH Policy ───
        'wfh_max_days_per_month' => ['value' => '4', 'type' => 'integer', 'category' => 'wfh', 'description' => 'จำนวนวัน WFH สูงสุดต่อเดือน'],
        'wfh_allowed_days' => ['value' => 'sat', 'type' => 'string', 'category' => 'wfh', 'description' => 'วันที่ WFH ได้ (sat=เสาร์, weekday=จ-ศ, all=ทุกวัน)'],
        'wfh_advance_days' => ['value' => '1', 'type' => 'integer', 'category' => 'wfh', 'description' => 'ต้องแจ้งล่วงหน้ากี่วัน'],

        // ─── Leave Policy ───
        'sick_leave_max_days' => ['value' => '30', 'type' => 'integer', 'category' => 'leave', 'description' => 'ลาป่วยสูงสุดต่อปี (วัน)'],
        'annual_leave_max_days' => ['value' => '6', 'type' => 'integer', 'category' => 'leave', 'description' => 'ลาพักร้อนสูงสุดต่อปี (วัน)'],
        'personal_leave_max_days' => ['value' => '6', 'type' => 'integer', 'category' => 'leave', 'description' => 'ลากิจสูงสุดต่อปี (วัน)'],
        'leave_advance_days' => ['value' => '1', 'type' => 'integer', 'category' => 'leave', 'description' => 'ต้องแจ้งล่วงหน้ากี่วันก่อนลา'],

        // ─── Approval Workflow ───
        'ot_approval_levels' => ['value' => '2', 'type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติ OT (1=HR, 2=หัวหน้า→HR)'],
        'leave_approval_levels' => ['value' => '2', 'type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติลางาน (1=HR, 2=หัวหน้า→HR)'],
        'wfh_approval_levels' => ['value' => '1', 'type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติ WFH (1=HR, 2=หัวหน้า→HR)'],
        'auto_approve_md' => ['value' => 'true', 'type' => 'boolean', 'category' => 'approval', 'description' => 'MD อนุมัติอัตโนมัติ (ไม่ต้องส่งหัวหน้า)'],

        // ─── Notification ───
        'notify_on_request' => ['value' => 'true', 'type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนหัวหน้าเมื่อลูกน้องส่งคำขอ'],
        'notify_on_approve' => ['value' => 'true', 'type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนพนักงานเมื่ออนุมัติ'],
        'notify_on_reject' => ['value' => 'true', 'type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนพนักงานเมื่อไม่อนุมัติ'],
    ];

    private static ?array $cache = null;

    /**
     * Get a config value with hardcoded fallback.
     * Always works — even if DB is down, table is empty, or config doesn't exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (isset($all[$key])) {
            return self::castValue($all[$key]['value'], $all[$key]['type'] ?? 'string');
        }
        return $default;
    }

    /**
     * Get all config values (from DB + hardcoded defaults).
     * DB values override hardcoded defaults.
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Start with hardcoded defaults
        $config = [];
        foreach (self::DEFAULTS as $key => $def) {
            $config[$key] = [
                'value' => $def['value'],
                'type' => $def['type'],
                'category' => $def['category'],
                'description' => $def['description'],
                'source' => 'default',
            ];
        }

        // Override with DB values (if table exists)
        try {
            if (DB::getSchemaBuilder()->hasTable('system_config')) {
                $dbConfigs = SystemConfig::all(['key', 'value', 'value_type', 'category', 'description', 'company_id']);
                foreach ($dbConfigs as $row) {
                    $config[$row->key] = [
                        'value' => $row->value,
                        'type' => $row->value_type ?? 'string',
                        'category' => $row->category,
                        'description' => $row->description ?? ($config[$row->key]['description'] ?? ''),
                        'source' => 'database',
                        'company_id' => $row->company_id,
                        'id' => $row->id ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('SystemConfig: Could not read from database, using defaults. Error: ' . $e->getMessage());
        }

        self::$cache = $config;
        return $config;
    }

    /**
     * Get all config grouped by category.
     */
    public static function grouped(): array
    {
        $all = self::all();
        $grouped = [];
        foreach ($all as $key => $item) {
            $cat = $item['category'] ?? 'general';
            $grouped[$cat][$key] = $item;
        }
        return $grouped;
    }

    /**
     * Set a config value (upsert to DB).
     */
    public static function set(string $key, mixed $value, ?int $companyId = null): bool
    {
        $default = self::DEFAULTS[$key] ?? null;
        $category = $default['category'] ?? 'general';
        $description = $default['description'] ?? '';
        $valueType = $default['type'] ?? 'string';

        try {
            SystemConfig::updateOrCreate(
                ['key' => $key, 'company_id' => $companyId],
                [
                    'value' => (string) $value,
                    'value_type' => $valueType,
                    'category' => $category,
                    'description' => $description,
                ]
            );
            self::$cache = null; // clear cache
            return true;
        } catch (\Exception $e) {
            Log::error('SystemConfig: Could not set key "' . $key . '". Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple config values at once.
     */
    public static function setMany(array $configs, ?int $companyId = null): bool
    {
        try {
            foreach ($configs as $key => $value) {
                self::set($key, $value, $companyId);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('SystemConfig: Error in setMany. Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear cache (call after config changes).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Cast value to appropriate PHP type.
     */
    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Check if a position should be excluded from attendance (uses config with fallback).
     */
    public static function isPositionExcluded(string $position): bool
    {
        $excluded = self::get('excluded_positions', 'chairman,md,executive_director,assistant_md');
        $positions = is_string($excluded) ? explode(',', $excluded) : $excluded;
        return in_array($position, $positions);
    }
}
