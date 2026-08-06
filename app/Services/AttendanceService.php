<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Constants\PositionConstants;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function checkIn(Employee $employee, ?string $latLong = null): AttendanceLog
    {
        $today = Carbon::today();
        $now = Carbon::now();
        $officeStartTime = Carbon::today()->setTime(8, 30, 0);

        $existingLog = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existingLog && $existingLog->check_in) {
            throw new \Exception('Already checked in today');
        }

        $isLate = $now->gt($officeStartTime);
        $status = $isLate ? 'late' : 'on_time';

        if ($existingLog) {
            $existingLog->update([
                'check_in' => $now->format('H:i:s'),
                'status' => $status,
                'lat_long' => $latLong ?? $existingLog->lat_long,
            ]);
            $log = $existingLog->fresh();
        } else {
            $log = AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'check_in' => $now->format('H:i:s'),
                'status' => $status,
                'lat_long' => $latLong,
            ]);
        }

        try {
            $this->telegramService->sendCheckInNotification($employee, $log);
        } catch (\Exception $e) {
            Log::error('Failed to send check-in notification: ' . $e->getMessage());
        }

        return $log;
    }

    public function checkOut(Employee $employee): AttendanceLog
    {
        $today = Carbon::today();

        $log = AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (!$log) {
            throw new \Exception('No check-in record found for today');
        }

        if ($log->check_out) {
            throw new \Exception('Already checked out today');
        }

        $now = Carbon::now();
        $log->update([
            'check_out' => $now->format('H:i:s'),
        ]);

        $log = $log->fresh();

        try {
            $this->telegramService->sendCheckOutNotification($employee, $log);
        } catch (\Exception $e) {
            Log::error('Failed to send check-out notification: ' . $e->getMessage());
        }

        return $log;
    }

    public function getTodayStats(int $companyId): array
    {
        $today = Carbon::today();

        $totalEmployees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $presentCount = AttendanceLog::whereHas('employee', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->whereDate('date', $today)
            ->whereNotNull('check_in')
            ->count();

        $lateCount = AttendanceLog::whereHas('employee', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->whereDate('date', $today)
            ->where('status', 'late')
            ->count();

        $onLeaveCount = \App\Models\Leave::whereHas('employee', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where('status', 'approved')
            ->count();

        $absentCount = $totalEmployees - $presentCount - $onLeaveCount;
        if ($absentCount < 0) {
            $absentCount = 0;
        }

        return [
            'total' => $totalEmployees,
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'on_leave' => $onLeaveCount,
        ];
    }

    public function getMonthlyStats(int $companyId, int $month, int $year): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $totalEmployees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $attendanceByDate = AttendanceLog::whereHas('employee', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('date, 
                COUNT(*) as total,
                SUM(CASE WHEN status = "on_time" THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late')
            ->groupBy('date')
            ->get();

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $workingDays = 0;
        $totalPresent = 0;
        $totalLate = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekday()) {
                $workingDays++;
                $dayStats = $attendanceByDate->firstWhere('date', $date->format('Y-m-d'));
                if ($dayStats) {
                    $totalPresent += $dayStats->total;
                    $totalLate += $dayStats->late;
                }
            }
        }

        $totalAbsent = ($workingDays * $totalEmployees) - $totalPresent;
        if ($totalAbsent < 0) {
            $totalAbsent = 0;
        }

        $totalOnLeave = \App\Models\Leave::whereHas('employee', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->count();

        return [
            'month' => $month,
            'year' => $year,
            'total_employees' => $totalEmployees,
            'working_days' => $workingDays,
            'total_present' => $totalPresent,
            'total_late' => $totalLate,
            'total_absent' => $totalAbsent,
            'total_on_leave' => $totalOnLeave,
            'attendance_rate' => $workingDays > 0
                ? round(($totalPresent / ($workingDays * $totalEmployees)) * 100, 2)
                : 0,
        ];
    }
}
