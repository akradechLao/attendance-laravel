<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'original_status')) {
                $table->string('original_status')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'final_status')) {
                $table->string('final_status')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'late_minutes')) {
                $table->integer('late_minutes')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'adjusted_by')) {
                $table->unsignedBigInteger('adjusted_by')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable();
            }
            if (!Schema::hasColumn('attendance_logs', 'adjustment_note')) {
                $table->text('adjustment_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $cols = ['original_status', 'final_status', 'late_minutes', 'adjusted_by', 'adjusted_at', 'adjustment_note'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('attendance_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
