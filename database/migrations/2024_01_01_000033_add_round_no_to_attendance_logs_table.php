<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่ม round_no column
        $hasRoundNo = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'round_no'");
        if (empty($hasRoundNo)) {
            DB::statement('ALTER TABLE attendance_logs ADD COLUMN round_no INT DEFAULT 1 AFTER emp_id');
        }

        // 2. สร้าง index ชั่วคราวบน emp_id เพียงตัวเดียว เพื่อรองรับ FK constraint
        //    เพราะ MySQL ใช้ composite unique index (emp_id, date) รองรับ FK อยู่
        $hasEmpIdIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'temp_emp_id_index'");
        if (empty($hasEmpIdIndex)) {
            DB::statement('ALTER TABLE attendance_logs ADD INDEX temp_emp_id_index (emp_id)');
        }

        // 3. Drop old unique index
        $hasUnique = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_emp_id_date_unique'");
        if (!empty($hasUnique)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_emp_id_date_unique');
        }

        // 4. Create new unique index with round_no
        $hasNewUnique = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_emp_id_date_round_no_unique'");
        if (empty($hasNewUnique)) {
            DB::statement('ALTER TABLE attendance_logs ADD UNIQUE KEY attendance_logs_emp_id_date_round_no_unique (emp_id, date, round_no)');
        }

        // 5. ลบ temp index
        $hasTempIndex = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'temp_emp_id_index'");
        if (!empty($hasTempIndex)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX temp_emp_id_index');
        }
    }

    public function down(): void
    {
        $hasNewUnique = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_emp_id_date_round_no_unique'");
        if (!empty($hasNewUnique)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_emp_id_date_round_no_unique');
        }
        DB::statement('ALTER TABLE attendance_logs ADD UNIQUE KEY attendance_logs_emp_id_date_unique (emp_id, date)');
        $hasRoundNo = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'round_no'");
        if (!empty($hasRoundNo)) {
            DB::statement('ALTER TABLE attendance_logs DROP COLUMN round_no');
        }
    }
};
