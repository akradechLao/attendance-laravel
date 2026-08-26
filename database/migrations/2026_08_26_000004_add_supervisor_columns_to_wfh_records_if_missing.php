<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wfh_records', function (Blueprint $table) {
            if (!Schema::hasColumn('wfh_records', 'supervisor_id')) {
                $table->foreignId('supervisor_id')->nullable()->after('status')->constrained('admin_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('wfh_records', 'supervisor_note')) {
                $table->string('supervisor_note')->nullable()->after('supervisor_id');
            }
            if (!Schema::hasColumn('wfh_records', 'approved_date')) {
                $table->date('approved_date')->nullable()->after('supervisor_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wfh_records', function (Blueprint $table) {
            if (Schema::hasColumn('wfh_records', 'supervisor_id')) {
                $table->dropForeign(['supervisor_id']);
                $table->dropColumn(['supervisor_id', 'supervisor_note', 'approved_date']);
            }
        });
    }
};
