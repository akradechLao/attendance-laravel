<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table) {
            if (Schema::hasColumn('shift_swaps', 'requester_shift_id')) {
                DB::statement('ALTER TABLE shift_swaps CHANGE COLUMN requester_shift_id requester_shift VARCHAR(50) NOT NULL');
            }
            if (Schema::hasColumn('shift_swaps', 'target_shift_id')) {
                DB::statement('ALTER TABLE shift_swaps CHANGE COLUMN target_shift_id target_shift VARCHAR(50) NOT NULL');
            }
            if (!Schema::hasColumn('shift_swaps', 'reason')) {
                $table->text('reason')->nullable()->after('target_shift');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_swaps', function (Blueprint $table) {
            if (Schema::hasColumn('shift_swaps', 'requester_shift')) {
                DB::statement('ALTER TABLE shift_swaps CHANGE COLUMN requester_shift requester_shift_id BIGINT UNSIGNED NOT NULL');
            }
            if (Schema::hasColumn('shift_swaps', 'target_shift')) {
                DB::statement('ALTER TABLE shift_swaps CHANGE COLUMN target_shift target_shift_id BIGINT UNSIGNED NOT NULL');
            }
            if (Schema::hasColumn('shift_swaps', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
