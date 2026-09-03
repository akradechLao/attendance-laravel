<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get work_shift_id for WC0015 (group_number=14, 07:00-16:00)
        $wc0015 = DB::table('work_shifts')->where('group_number', 14)->first();
        if (!$wc0015) {
            return;
        }

        // Get employee IDs for 007 and 008
        $employees = DB::table('employees')
            ->whereIn('employee_code', ['007', '008'])
            ->pluck('id', 'employee_code');

        foreach ($employees as $code => $empId) {
            $exists = DB::table('employee_shifts')
                ->where('employee_id', $empId)
                ->where('work_shift_id', $wc0015->id)
                ->exists();

            if (!$exists) {
                DB::table('employee_shifts')->insert([
                    'employee_id' => $empId,
                    'work_shift_id' => $wc0015->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Fix existing attendance_logs: recalculate late based on 07:00 start
        // If check_in < 07:00 → late = 0 (on_time)
        // If check_in >= 07:00 → late = minutes since 07:00
        $empIds = $employees->values();
        $logs = DB::table('attendance_logs')
            ->whereIn('emp_id', $empIds)
            ->where('check_in_status', 'late')
            ->whereNotNull('check_in')
            ->get();

        foreach ($logs as $log) {
            $checkInTime = $log->check_in; // HH:MM:SS or HH:MM
            $parts = explode(':', $checkInTime);
            $checkInMinutes = ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
            // 07:00 = 7*60 = 420 minutes
            $workStartMinutes = 7 * 60; // 07:00

            $lateMinutes = max(0, $checkInMinutes - $workStartMinutes);

            if ($lateMinutes <= 0) {
                // Employee was on time (checked in before 07:00)
                DB::table('attendance_logs')
                    ->where('id', $log->id)
                    ->update([
                        'check_in_status' => 'on_time',
                        'original_status' => 'on_time',
                        'final_status' => 'on_time',
                        'late_minutes' => null,
                    ]);
            } else {
                // Update late_minutes with correct value
                DB::table('attendance_logs')
                    ->where('id', $log->id)
                    ->update([
                        'late_minutes' => $lateMinutes,
                    ]);
            }
        }

        // Fix LateForcedLeave: recalculate or delete if no longer applicable
        // Forced leave only applies if late > 30 minutes
        $forcedLeaves = DB::table('late_forced_leaves')
            ->whereIn('emp_id', $empIds)
            ->get();

        foreach ($forcedLeaves as $fl) {
            // Find the corresponding attendance log
            $log = DB::table('attendance_logs')
                ->where('emp_id', $fl->emp_id)
                ->where('date', $fl->date)
                ->whereNotNull('check_in')
                ->first();

            if (!$log) {
                // No log found, delete forced leave
                DB::table('late_forced_leaves')->where('id', $fl->id)->delete();
                continue;
            }

            $parts = explode(':', $log->check_in);
            $checkInMinutes = ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));
            $workStartMinutes = 7 * 60; // 07:00
            $actualLate = max(0, $checkInMinutes - $workStartMinutes);

            if ($actualLate <= 30) {
                // No longer qualifies for forced leave
                DB::table('late_forced_leaves')->where('id', $fl->id)->delete();
            } else {
                // Update late_minutes in forced leave
                DB::table('late_forced_leaves')
                    ->where('id', $fl->id)
                    ->update(['late_minutes' => $actualLate]);
            }
        }
    }

    public function down(): void
    {
        $employees = DB::table('employees')
            ->whereIn('employee_code', ['007', '008'])
            ->pluck('id');

        $wc0015 = DB::table('work_shifts')->where('group_number', 14)->first();
        if ($wc0015) {
            DB::table('employee_shifts')
                ->whereIn('employee_id', $employees)
                ->where('work_shift_id', $wc0015->id)
                ->delete();
        }
    }
};
