<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'is_verified')) {
                $table->boolean('is_verified')->default(false);
            }
            if (!Schema::hasColumn('attendance_logs', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $cols = ['verified_at', 'verified_by', 'is_verified'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('attendance_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
