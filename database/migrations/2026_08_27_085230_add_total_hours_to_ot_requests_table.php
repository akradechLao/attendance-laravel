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
            $table->decimal('total_hours', 5, 2)->nullable()->after('end_time');
        });

        DB::statement('UPDATE ot_requests SET total_hours = TIMESTAMPDIFF(MINUTE, CONCAT(date, " ", start_time), CONCAT(date, " ", end_time)) / 60.0');
    }

    public function down(): void
    {
        Schema::table('ot_requests', function (Blueprint $table) {
            $table->dropColumn('total_hours');
        });
    }
};
