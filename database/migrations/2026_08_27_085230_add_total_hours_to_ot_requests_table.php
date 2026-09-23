<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ot_requests', 'total_hours')) {
            Schema::table('ot_requests', fn(Blueprint $t) => $t->decimal('total_hours', 5, 2)->nullable());
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('UPDATE ot_requests SET total_hours = TIMESTAMPDIFF(MINUTE, CONCAT(date, " ", start_time), CONCAT(date, " ", end_time)) / 60.0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ot_requests', 'total_hours')) {
            Schema::table('ot_requests', fn(Blueprint $t) => $t->dropColumn('total_hours'));
        }
    }
};
