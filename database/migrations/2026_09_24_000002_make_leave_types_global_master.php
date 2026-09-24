<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function ($table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        // Consolidate per-company duplicates into one global master row per code
        $groups = DB::table('leave_types')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($r) => $r->code ?: ('name:' . $r->name));

        foreach ($groups as $group) {
            if ($group->count() < 2 && $group->first()->company_id === null) {
                continue;
            }

            $masterId = $group->first()->id;
            $otherIds = $group->pluck('id')->reject(fn ($id) => $id === $masterId)->values();

            if ($otherIds->isNotEmpty()) {
                // Remap FK references before delete (unique on emp/leave_type/year is safe:
                // each employee only ever has one company's leave types)
                DB::table('leave_balances')
                    ->whereIn('leave_type_id', $otherIds)
                    ->update(['leave_type_id' => $masterId]);
                DB::table('leave_requests')
                    ->whereIn('leave_type_id', $otherIds)
                    ->update(['leave_type_id' => $masterId]);
                DB::table('leave_types')->whereIn('id', $otherIds)->delete();
            }

            DB::table('leave_types')->where('id', $masterId)->update(['company_id' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', function ($table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }
};
