<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance_logs', 'company_id')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable();
                $table->index('company_id');
            });
        }

        DB::table('attendance_logs')
            ->whereNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                $empIds = $logs->pluck('emp_id')->unique()->filter();
                if ($empIds->isEmpty()) return;
                $map = DB::table('employees')->whereIn('id', $empIds)->pluck('company_id', 'id');
                foreach ($logs as $log) {
                    if (isset($map[$log->emp_id])) {
                        DB::table('attendance_logs')->where('id', $log->id)->update(['company_id' => $map[$log->emp_id]]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_logs', 'company_id')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
