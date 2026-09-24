<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'gender')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('gender', 20)->nullable()->after('birth_date')->index();
            });
        }

        // Backfill gender from Thai name titles where still unknown
        DB::table('employees')
            ->whereNull('gender')
            ->where(function ($q) {
                $q->where('name', 'like', 'นาย%')
                    ->orWhere('name', 'like', 'เด็กชาย%')
                    ->orWhere('name', 'like', 'ด.ช.%');
            })
            ->update(['gender' => 'male']);

        DB::table('employees')
            ->whereNull('gender')
            ->where(function ($q) {
                $q->where('name', 'like', 'นางสาว%')
                    ->orWhere('name', 'like', 'นาง %')
                    ->orWhere('name', 'like', 'เด็กหญิง%')
                    ->orWhere('name', 'like', 'ด.ญ.%');
            })
            ->update(['gender' => 'female']);

        // Production still has 90 days; force maternity to 120 per current law
        DB::table('leave_types')
            ->where('code', 'maternity')
            ->update([
                'max_days' => 120,
                'max_days_per_year' => 120,
                'updated_at' => now(),
            ]);

        $maternityIds = DB::table('leave_types')->where('code', 'maternity')->pluck('id');
        if ($maternityIds->isNotEmpty()) {
            DB::table('leave_balances')
                ->whereIn('leave_type_id', $maternityIds)
                ->where('entitled_days', '<', 120)
                ->update(['entitled_days' => 120]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'gender')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }
    }
};
