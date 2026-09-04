<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!$table->hasColumn('is_estimated')) {
                $table->boolean('is_estimated')->default(false)->after('pdpa_consent');
            }
            if (!$table->hasColumn('estimated_approved_by')) {
                $table->unsignedBigInteger('estimated_approved_by')->nullable()->after('is_estimated');
            }
            if (!$table->hasColumn('estimated_approved_at')) {
                $table->timestamp('estimated_approved_at')->nullable()->after('estimated_approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['is_estimated', 'estimated_approved_by', 'estimated_approved_at']);
        });
    }
};
