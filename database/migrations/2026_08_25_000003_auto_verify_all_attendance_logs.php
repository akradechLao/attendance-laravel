<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_logs')->where('is_verified', false)->update(['is_verified' => true]);
    }

    public function down(): void
    {
        // No-op: can't reverse auto-verify
    }
};
