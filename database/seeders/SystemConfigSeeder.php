<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        $companies = DB::table('companies')->pluck('id')->toArray();

        // Default config values (global — company_id = null)
        $globalConfigs = [
            // Attendance
            ['key' => 'late_grace_minutes', 'value' => '0', 'value_type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีที่สายแล้วยังไม่ถูกนับเป็นสาย (grace period)'],
            ['key' => 'late_threshold_minutes', 'value' => '30', 'value_type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีที่สายเกินกว่าจะถูกหักลา'],
            ['key' => 'forced_leave_minutes', 'value' => '60', 'value_type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนนาทีของลาบังคับเมื่อสายเกิน threshold'],
            ['key' => 'max_check_in_rounds', 'value' => '4', 'value_type' => 'integer', 'category' => 'attendance', 'description' => 'จำนวนรอบสูงสุดที่เช็คอิน/เช็คเอาท์ได้ต่อวัน'],
            ['key' => 'auto_checkout_time', 'value' => '', 'value_type' => 'string', 'category' => 'attendance', 'description' => 'เวลาที่ระบบจะ auto-checkout (เช่น 18:00). ว่าง = ไม่ auto'],

            // OT
            ['key' => 'max_ot_hours_per_day', 'value' => '4', 'value_type' => 'integer', 'category' => 'ot', 'description' => 'ชั่วโมง OT สูงสุดต่อวัน'],
            ['key' => 'max_ot_hours_per_week', 'value' => '36', 'value_type' => 'integer', 'category' => 'ot', 'description' => 'ชั่วโมง OT สูงสุดต่อสัปดาห์'],
            ['key' => 'ot_minimum_minutes', 'value' => '60', 'value_type' => 'integer', 'category' => 'ot', 'description' => 'จำนวนนาทีขั้นต่ำของ OT'],
            ['key' => 'ot_requires_approval', 'value' => 'true', 'value_type' => 'boolean', 'category' => 'ot', 'description' => 'OT ต้องได้รับอนุมัติก่อนหรือไม่'],

            // WFH
            ['key' => 'wfh_max_days_per_month', 'value' => '4', 'value_type' => 'integer', 'category' => 'wfh', 'description' => 'จำนวนวัน WFH สูงสุดต่อเดือน'],
            ['key' => 'wfh_allowed_days', 'value' => 'sat', 'value_type' => 'string', 'category' => 'wfh', 'description' => 'วันที่ WFH ได้ (sat=เสาร์, weekday=จ-ศ, all=ทุกวัน)'],
            ['key' => 'wfh_advance_days', 'value' => '1', 'value_type' => 'integer', 'category' => 'wfh', 'description' => 'ต้องแจ้งล่วงหน้ากี่วัน'],

            // Leave
            ['key' => 'sick_leave_max_days', 'value' => '30', 'value_type' => 'integer', 'category' => 'leave', 'description' => 'ลาป่วยสูงสุดต่อปี (วัน)'],
            ['key' => 'annual_leave_max_days', 'value' => '6', 'value_type' => 'integer', 'category' => 'leave', 'description' => 'ลาพักร้อนสูงสุดต่อปี (วัน)'],
            ['key' => 'personal_leave_max_days', 'value' => '6', 'value_type' => 'integer', 'category' => 'leave', 'description' => 'ลากิจสูงสุดต่อปี (วัน)'],
            ['key' => 'leave_advance_days', 'value' => '1', 'value_type' => 'integer', 'category' => 'leave', 'description' => 'ต้องแจ้งล่วงหน้ากี่วันก่อนลา'],

            // Approval
            ['key' => 'ot_approval_levels', 'value' => '2', 'value_type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติ OT (1=HR, 2=หัวหน้า→HR)'],
            ['key' => 'leave_approval_levels', 'value' => '2', 'value_type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติลางาน'],
            ['key' => 'wfh_approval_levels', 'value' => '1', 'value_type' => 'integer', 'category' => 'approval', 'description' => 'จำนวนลำดับอนุมัติ WFH'],
            ['key' => 'auto_approve_md', 'value' => 'true', 'value_type' => 'boolean', 'category' => 'approval', 'description' => 'MD อนุมัติอัตโนมัติ'],

            // Notification
            ['key' => 'notify_on_request', 'value' => 'true', 'value_type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนหัวหน้าเมื่อลูกน้องส่งคำขอ'],
            ['key' => 'notify_on_approve', 'value' => 'true', 'value_type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนพนักงานเมื่ออนุมัติ'],
            ['key' => 'notify_on_reject', 'value' => 'true', 'value_type' => 'boolean', 'category' => 'notification', 'description' => 'แจ้งเตือนพนักงานเมื่อไม่อนุมัติ'],
        ];

        $count = 0;
        foreach ($globalConfigs as $cfg) {
            $exists = DB::table('system_config')
                ->whereNull('company_id')
                ->where('key', $cfg['key'])
                ->exists();

            if (!$exists) {
                DB::table('system_config')->insert(
                    array_merge($cfg, [
                        'company_id' => null,
                        'is_system' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
                $count++;
            }
        }

        $this->command->info("{$count} global system config seeded.");
    }
}
