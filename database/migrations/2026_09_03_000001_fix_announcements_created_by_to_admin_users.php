<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('announcements', fn(Blueprint $table) => $table->dropForeign(['created_by']));
        } catch (\Exception $e) {
            // SQLite or no FK exists
        }

        DB::table('announcements')
            ->whereNotNull('created_by')
            ->whereNotIn('created_by', DB::table('admin_users')->select('id'))
            ->update(['created_by' => null]);

        try {
            Schema::table('announcements', fn(Blueprint $table) => $table->foreign('created_by')->references('id')->on('admin_users')->nullOnDelete());
        } catch (\Exception $e) {
            // SQLite FK recreation may fail - safe to ignore
        }
    }

    public function down(): void
    {
        // No-op for local dev
    }
};
