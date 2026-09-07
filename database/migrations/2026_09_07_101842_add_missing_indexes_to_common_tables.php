<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ot_requests: queried by emp_id + status + date
        Schema::table('ot_requests', function (Blueprint $table) {
            $table->index('emp_id');
            $table->index(['status', 'date']);
        });

        // leave_requests: queried by emp_id + status + date range
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index('emp_id');
            $table->index(['emp_id', 'status']);
            $table->index(['status', 'start_date']);
        });

        // wfh_records: queried by status in availableSaturdays
        Schema::table('wfh_records', function (Blueprint $table) {
            $table->index('emp_id');
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            $table->dropIndex(['emp_id']);
            $table->dropIndex(['status', 'date']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['emp_id']);
            $table->dropIndex(['emp_id', 'status']);
            $table->dropIndex(['status', 'start_date']);
        });

        Schema::table('wfh_records', function (Blueprint $table) {
            $table->dropIndex(['emp_id']);
            $table->dropIndex(['status', 'date']);
        });
    }
};
