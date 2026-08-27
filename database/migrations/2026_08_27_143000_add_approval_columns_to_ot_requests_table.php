<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            if (!$table->hasColumn('manager_approved_by')) {
                $table->unsignedBigInteger('manager_approved_by')->nullable()->after('status');
            }
            if (!$table->hasColumn('manager_approved_at')) {
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            }
            if (!$table->hasColumn('hr_approved_by')) {
                $table->unsignedBigInteger('hr_approved_by')->nullable()->after('manager_approved_at');
            }
            if (!$table->hasColumn('hr_approved_at')) {
                $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by');
            }
            if (!$table->hasColumn('rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('hr_approved_at');
            }
            if (!$table->hasColumn('rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejection_reason');
            }
            if (!$table->hasColumn('rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            $table->dropColumn([
                'manager_approved_by', 'manager_approved_at',
                'hr_approved_by', 'hr_approved_at',
                'rejection_reason', 'rejected_by', 'rejected_at',
            ]);
        });
    }
};
