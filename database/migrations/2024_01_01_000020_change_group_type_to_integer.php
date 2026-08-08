<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type INTEGER NOT NULL DEFAULT 0 COMMENT '0-6 = work schedule group'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type ENUM('A','B') NOT NULL DEFAULT 'B'");
    }
};
