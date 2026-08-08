<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type INTEGER NOT NULL DEFAULT 1 COMMENT '0-9 = work shift group'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type ENUM('A','B') NOT NULL DEFAULT 'B'");
    }
};
