<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('emp_id');
            $table->index('company_id');
        });

        // Backfill from employees table
        DB::statement('
            UPDATE attendance_logs al
            JOIN employees e ON al.emp_id = e.id
            SET al.company_id = e.company_id
            WHERE al.company_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
