<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            $columns = [];
            if (!Schema::hasColumn('ot_requests', 'manager_approved_by')) {
                $columns[] = 'manager_approved_by';
            }
            if (!Schema::hasColumn('ot_requests', 'manager_approved_at')) {
                $columns[] = 'manager_approved_at';
            }
            if (!Schema::hasColumn('ot_requests', 'hr_approved_by')) {
                $columns[] = 'hr_approved_by';
            }
            if (!Schema::hasColumn('ot_requests', 'hr_approved_at')) {
                $columns[] = 'hr_approved_at';
            }
            if (!Schema::hasColumn('ot_requests', 'rejection_reason')) {
                $columns[] = 'rejection_reason';
            }
            if (!Schema::hasColumn('ot_requests', 'rejected_by')) {
                $columns[] = 'rejected_by';
            }
            if (!Schema::hasColumn('ot_requests', 'rejected_at')) {
                $columns[] = 'rejected_at';
            }

            if (in_array('manager_approved_by', $columns)) {
                $table->unsignedBigInteger('manager_approved_by')->nullable()->after('status');
            }
            if (in_array('manager_approved_at', $columns)) {
                $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            }
            if (in_array('hr_approved_by', $columns)) {
                $table->unsignedBigInteger('hr_approved_by')->nullable()->after('manager_approved_at');
            }
            if (in_array('hr_approved_at', $columns)) {
                $table->timestamp('hr_approved_at')->nullable()->after('hr_approved_by');
            }
            if (in_array('rejection_reason', $columns)) {
                $table->text('rejection_reason')->nullable()->after('hr_approved_at');
            }
            if (in_array('rejected_by', $columns)) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejection_reason');
            }
            if (in_array('rejected_at', $columns)) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            $columns = [];
            foreach (['manager_approved_by', 'manager_approved_at', 'hr_approved_by', 'hr_approved_at', 'rejection_reason', 'rejected_by', 'rejected_at'] as $col) {
                if (Schema::hasColumn('ot_requests', $col)) {
                    $columns[] = $col;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
