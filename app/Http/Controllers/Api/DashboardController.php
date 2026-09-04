<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Company;
use App\Models\LateForcedLeave;
use App\Models\OtRequest;
use App\Helpers\AttendanceCalculator;
use App\Helpers\AttendanceHelper;
use App\Services\ShiftResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');
            $today = Carbon::now('Asia/Bangkok')->today()->toDateString();
            $yesterday = Carbon::now('Asia/Bangkok')->yesterday()->toDateString();

            $employeeQuery = Employee::where('is_active', true);
            if ($companyId) {
                $employeeQuery->where('company_id', $companyId);
            }
            $totalEmployees = $employeeQuery->count();

            // ดึงข้อมูลวันนี้
            $todayQuery = AttendanceLog::where('date', $today)
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            // Present today (has today's record)
            $presentToday = (clone $todayQuery)->pluck('emp_id')->unique()->count();
            $lateToday = (clone $todayQuery)->where('check_in_status', 'late')->pluck('emp_id')->unique()->count();
            $onTimeToday = $presentToday - $lateToday;
            $checkedOut = (clone $todayQuery)->whereNotNull('check_out')->pluck('emp_id')->unique()->count();
            // Overnight workers: have yesterday's unchecked-out record + overnight shift + no today record
            $overnightOnly = AttendanceLog::where('date', $yesterday)
                ->whereNull('check_out')
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->get()
                ->filter(function ($log) use ($today) {
                    $employee = $log->employee;
                    if (!$employee) return false;
                    $shiftInfo = \App\Services\ShiftResolver::resolve($employee, $log->date instanceof Carbon ? $log->date->toDateString() : $log->date);
                    if (!($shiftInfo['is_overnight'] ?? false)) return false;
                    return !AttendanceLog::where('emp_id', $employee->id)->where('date', $today)->exists();
                })
                ->pluck('emp_id')->unique();
            $presentToday += $overnightOnly->count();
            $absentToday = max(0, $totalEmployees - $presentToday);

            // นับลากิจบังคับวันนี้ + กะข้ามคืน
            $forcedLeaveQuery = LateForcedLeave::where(function ($q) use ($today, $yesterday) {
                    $q->where('date', $today)
                      ->orWhere('date', $yesterday);
                })
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });
            $forcedLeavesPending = (clone $forcedLeaveQuery)->where('status', 'pending')->count();
            $forcedLeavesApproved = (clone $forcedLeaveQuery)->where('status', 'approved')->count();

            $monthStart = Carbon::now('Asia/Bangkok')->startOfMonth()->toDateString();
            $monthEnd = Carbon::now('Asia/Bangkok')->endOfMonth()->toDateString();

            $monthlyQuery = AttendanceLog::whereBetween('date', [$monthStart, $monthEnd])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });
            $monthlyAttendance = (clone $monthlyQuery)->get();
            $monthlyLate = $monthlyAttendance->where('check_in_status', 'late')->count();
            $monthlyOnTime = $monthlyAttendance->where('check_in_status', 'on_time')->count();

            // Monthly OT hours
            $otQuery = OtRequest::where('status', 'approved')
                ->whereBetween('date', [$monthStart, $monthEnd]);
            if ($companyId) {
                $otQuery->where('company_id', $companyId);
            }
            $monthlyOtHours = $otQuery->sum('total_hours');

            $companies = Company::all();
            $companyStats = [];
            foreach ($companies as $company) {
                $totalQ = Employee::where('company_id', $company->id)->where('is_active', true);
                $total = $totalQ->count();

                // Today's records for this company
                $todayPresentQ = AttendanceLog::where('date', $today)
                    ->whereHas('employee', function ($q) use ($company) {
                        $q->where('company_id', $company->id)->where('is_active', true);
                    });
                $present = (clone $todayPresentQ)->pluck('emp_id')->unique()->count();
                $late = (clone $todayPresentQ)->where('check_in_status', 'late')->pluck('emp_id')->unique()->count();

                // Overnight-only: yesterday unchecked + overnight shift + no today record
                $overnightOnly = AttendanceLog::where('date', $yesterday)
                    ->whereNull('check_out')
                    ->whereHas('employee', function ($q) use ($company) {
                        $q->where('company_id', $company->id)->where('is_active', true);
                    })
                    ->get()
                    ->filter(function ($log) use ($today) {
                        $employee = $log->employee;
                        if (!$employee) return false;
                        $shiftInfo = \App\Services\ShiftResolver::resolve($employee, $log->date instanceof Carbon ? $log->date->toDateString() : $log->date);
                        if (!($shiftInfo['is_overnight'] ?? false)) return false;
                        return !AttendanceLog::where('emp_id', $employee->id)->where('date', $today)->exists();
                    })
                    ->pluck('emp_id')->unique();
                $present += $overnightOnly->count();

                $companyStats[] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'code_prefix' => $company->code_prefix,
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'on_time' => $present - $late,
                    'absent' => max(0, $total - $present),
                    'percent' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'today' => [
                        'present' => $presentToday,
                        'late' => $lateToday,
                        'on_time' => $onTimeToday,
                        'checked_out' => $checkedOut,
                        'absent' => $absentToday,
                        'forced_leaves_pending' => $forcedLeavesPending,
                        'forced_leaves_approved' => $forcedLeavesApproved,
                    ],
                    'monthly' => [
                        'late' => $monthlyLate,
                        'on_time' => $monthlyOnTime,
                        'total_records' => $monthlyAttendance->count(),
                        'ot_hours' => $monthlyOtHours,
                    ],
                    'companies' => $companyStats,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function today(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();
            $companyId = $request->get('company_id');

            // ดึงข้อมูลวันนี้
            $query = AttendanceLog::where('date', $today)
                ->with(['employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->orderBy('round_no', 'asc')
                ->orderBy('check_in', 'desc');

            $logs = $query->get();

            // ─── Overnight workers: เมื่อวาน check_out=NULL + shift เป็น overnight ───
            $yesterdayUnchecked = AttendanceLog::where('date', $yesterday)
                ->whereNull('check_out')
                ->with(['employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->get()
                ->filter(function ($log) use ($today) {
                    // เฉพาะ overnight shift เท่านั้น (ข้ามคืน = shift end time < shift start time)
                    $employee = $log->employee;
                    if (!$employee) return false;
                    $shiftInfo = \App\Services\ShiftResolver::resolve($employee, $log->date instanceof Carbon ? $log->date->toDateString() : $log->date);
                    if (!($shiftInfo['is_overnight'] ?? false)) return false;
                    // ต้องไม่มี record วันนี้อยู่แล้ว
                    return !AttendanceLog::where('emp_id', $employee->id)->where('date', $today)->exists();
                });

            $logs = $logs->concat($yesterdayUnchecked);

            // Group by employee, combine rounds
            $grouped = $logs->groupBy('emp_id');
            $records = $grouped->map(function ($empLogs) use ($today, $yesterday) {
                $employee = $empLogs->first()->employee;
                if (!$employee) return null;

                // Separate logs by date
                $todayLogs = $empLogs->filter(fn($log) => ($log->date instanceof Carbon ? $log->date->toDateString() : $log->date) === $today);
                $hasToday = $todayLogs->isNotEmpty();

                // If has today's records → use only today (ignore yesterday's mistake/old data)
                // If no today's records → use yesterday's (overnight worker still on shift)
                $activeLogs = $hasToday ? $todayLogs : $empLogs;
                $activeDate = $hasToday ? $today : $yesterday;

                $firstIn = AttendanceHelper::getFirstCheckIn($employee->id, $activeDate);
                $lastOut = AttendanceHelper::getLastCheckOut($employee->id, $activeDate);
                $workedHours = AttendanceHelper::calculateWorkedHours($employee->id, $activeDate);

                $hasLate = $activeLogs->contains('check_in_status', 'late') ||
                           $activeLogs->contains('original_status', 'late');
                $lateMinutes = $activeLogs->min('late_minutes') ?? 0;

                $checkInFormatted = $firstIn
                    ? Carbon::parse($firstIn)->setTimezone('Asia/Bangkok')->format('H:i')
                    : '-';
                $checkOutFormatted = $lastOut
                    ? Carbon::parse($lastOut)->setTimezone('Asia/Bangkok')->format('H:i')
                    : '-';

                // Use today for shift resolution, fallback to active date
                $resolved = ShiftResolver::resolve($employee, $activeDate);

                return [
                    'id' => $empLogs->first()->id,
                    'employee_name' => $employee->name ?? '-',
                    'employee_code' => $employee->employee_code ?? '-',
                    'company_name' => $employee->company->name ?? '-',
                    'company_code' => $employee->company->code_prefix ?? '-',
                    'date' => $today,
                    'check_in' => $checkInFormatted,
                    'check_out' => $checkOutFormatted,
                    'original_status' => $hasLate ? 'late' : 'on_time',
                    'final_status' => $hasLate ? 'late' : 'on_time',
                    'late_minutes' => $lateMinutes,
                    'scan_type' => $empLogs->first()->scan_type,
                    'is_late' => $hasLate,
                    'has_forced_leave' => method_exists($empLogs->first(), 'lateForcedLeave') ? $empLogs->first()->lateForcedLeave()->exists() : false,
                    'work_minutes' => $workedHours > 0 ? (int) ($workedHours * 60) : null,
                    'work_hours_display' => $workedHours > 0 ? AttendanceCalculator::formatMinutes((int) ($workedHours * 60)) : '-',
                    'shift_code' => $resolved['shift_code'],
                    'shift_time' => ($resolved['start_time'] && $resolved['end_time'])
                        ? $resolved['start_time'] . '-' . $resolved['end_time']
                        : '-',
                    'is_estimated' => $empLogs->first()->is_estimated ?? false,
                    'estimated_approved_by' => $empLogs->first()->estimated_approved_by ?? null,
                ];
            })->filter()->values();

            $present = $records->count();

            $totalQuery = Employee::where('is_active', true);
            if ($companyId) {
                $totalQuery->where('company_id', $companyId);
            }
            $totalEmployees = $totalQuery->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $today,
                    'total_employees' => $totalEmployees,
                    'present' => $present,
                    'absent' => max(0, $totalEmployees - $present),
                    'records' => $records,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve today\'s summary: ' . $e->getMessage(),
            ], 500);
        }
    }
}
