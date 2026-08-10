<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. เพิ่ม round_no column ก่อน
        $hasRoundNo = DB::select("SHOW COLUMNS FROM attendance_logs LIKE 'round_no'");
        if (empty($hasRoundNo)) {
            DB::statement('ALTER TABLE attendance_logs ADD COLUMN round_no INT DEFAULT 1 AFTER emp_id');
        }

        // 2. หา foreign key constraints ที่ reference ตารางนี้
        $fks = DB::select("
            SELECT CONSTRAINT_NAME, TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME = 'attendance_logs'
            AND REFERENCED_COLUMN_NAME IN ('emp_id', 'date')
            AND TABLE_SCHEMA = DATABASE()
        ");

        // 3. Drop foreign keys ชั่วคราว
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // 4. Drop old unique index
        $indexes = DB::select("SHOW INDEX FROM attendance_logs WHERE Key_name = 'attendance_logs_emp_id_date_unique'");
        if (!empty($indexes)) {
            DB::statement('ALTER TABLE attendance_logs DROP INDEX attendance_logs_emp_id_date_unique');
        }

        // 5. Create new unique index with round_no
        DB::statement('ALTER TABLE attendance_logs ADD UNIQUE KEY attendance_logs_emp_id_date_round_no_unique (emp_id, date, round_no)');

        // 6. Re-create foreign keys
        foreach ($fks as $fk) {
            $cols = DB::select("
                SELECT COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE CONSTRAINT_NAME = '{$fk->CONSTRAINT_NAME}'
                AND TABLE_NAME = '{$fk->TABLE_NAME}'
                AND TABLE_SCHEMA = DATABASE()
                ORDER BY ORDINAL_POSITION
            ");
            $colNames = array_map(fn($c) => "`{$c->COLUMN_NAME}`", $cols);
            $refCols = DB::select("
                SELECT REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE CONSTRAINT_NAME = '{$fk->CONSTRAINT_NAME}'
                AND TABLE_NAME = '{$fk->TABLE_NAME}'
                AND TABLE_SCHEMA = DATABASE()
                ORDER BY ORDINAL_POSITION
            ");
            $refNames = array_map(fn($c) => "`{$c->REFERENCED_COLUMN_NAME}`", $refCols);

            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` ADD CONSTRAINT `{$fk->CONSTRAINT_NAME}` FOREIGN KEY ({implode(',', $colNames)}) REFERENCES `attendance_logs` ({implode(',', $refNames)})");
        }
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['emp_id', 'date', 'round_no']);
            $table->unique(['emp_id', 'date']);
            $table->dropColumn('round_no');
        });
    }
};
