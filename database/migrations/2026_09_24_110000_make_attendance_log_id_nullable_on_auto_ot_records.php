<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_ot_records', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_log_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('auto_ot_records', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_log_id')->nullable(false)->change();
        });
    }
};
