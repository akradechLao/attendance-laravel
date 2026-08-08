<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type VARCHAR(2) NOT NULL DEFAULT 'A' COMMENT 'AA,A,B,C,D,E,F,G = work schedule group'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employees MODIFY COLUMN group_type ENUM('A','B') NOT NULL DEFAULT 'B'");
    }
};
