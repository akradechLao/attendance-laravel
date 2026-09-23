<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        if (Schema::hasColumn('shift_swaps', 'supervisor_id')) {
            $hasFk = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'shift_swaps' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%supervisor%'");
            if (!empty($hasFk) && $hasFk[0]->cnt > 0) {
                Schema::table('shift_swaps', function (Blueprint $table) {
                    $table->dropForeign(['supervisor_id']);
                    $table->foreign('supervisor_id')->references('id')->on('admin_users')->nullOnDelete();
                });
            }
        }

        if (Schema::hasColumn('auto_ot_records', 'approved_by')) {
            $hasFk = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'auto_ot_records' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%approved%'");
            if (!empty($hasFk) && $hasFk[0]->cnt > 0) {
                Schema::table('auto_ot_records', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                    $table->foreign('approved_by')->references('id')->on('admin_users')->nullOnDelete();
                });
            }
        }

        if (Schema::hasColumn('late_forced_leaves', 'approved_by')) {
            $hasFk = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'late_forced_leaves' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%approved%'");
            if (!empty($hasFk) && $hasFk[0]->cnt > 0) {
                Schema::table('late_forced_leaves', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                    $table->foreign('approved_by')->references('id')->on('admin_users')->nullOnDelete();
                });
            }
        }

        if (Schema::hasColumn('remote_assignments', 'approved_by')) {
            $hasFk = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'remote_assignments' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME LIKE '%approved%'");
            if (!empty($hasFk) && $hasFk[0]->cnt > 0) {
                Schema::table('remote_assignments', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                    $table->foreign('approved_by')->references('id')->on('admin_users')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::table('shift_swaps', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('auto_ot_records', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('late_forced_leaves', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
        });
        Schema::table('remote_assignments', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
        });
    }
};
