<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        DB::statement('UPDATE announcements SET created_by = NULL WHERE created_by IS NOT NULL AND created_by NOT IN (SELECT id FROM admin_users)');

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
        });
    }
};
