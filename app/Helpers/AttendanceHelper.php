<?php

namespace App\Helpers;

use App\Models\AttendanceLog;
use Carbon\Carbon;

class AttendanceHelper
{
    const BREAK_MINUTES = 60;

    public static function calculateWorkedHours(int $empId, string $date, ?string $untilTime = null): ?float
    {
        $logs = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_in')
            ->orderBy('check_in', 'asc')
            ->get();

        if ($logs->isEmpty()) return null;

        $firstIn = Carbon::parse($logs->first()->check_in);

        $lastOut = null;
        foreach ($logs as $log) {
            if ($log->check_out) {
                $out = Carbon::parse($log->check_out);
                if (!$lastOut || $out->gt($lastOut)) {
                    $lastOut = $out;
                }
            }
        }

        if (!$lastOut) {
            $lastOut = $untilTime ? Carbon::parse($untilTime) : Carbon::now();
        }

        $totalMinutes = $firstIn->diffInMinutes($lastOut) - self::BREAK_MINUTES;
        $totalMinutes = max(0, $totalMinutes);

        return round($totalMinutes / 60, 1);
    }

    public static function getFirstCheckIn(int $empId, string $date): ?string
    {
        $log = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_in')
            ->orderBy('check_in', 'asc')
            ->first();

        return $log?->check_in;
    }

    public static function getLastCheckOut(int $empId, string $date): ?string
    {
        $log = AttendanceLog::where('emp_id', $empId)
            ->where('date', $date)
            ->whereNotNull('check_out')
            ->orderBy('check_out', 'desc')
            ->first();

        return $log?->check_out;
    }
}
