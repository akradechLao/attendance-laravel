<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_locations', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
            $table->time('work_start_time')->nullable()->after('radius_meters');
            $table->time('work_end_time')->nullable()->after('work_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('office_locations', function (Blueprint $table) {
            $table->dropColumn(['address', 'work_start_time', 'work_end_time']);
        });
    }
};
