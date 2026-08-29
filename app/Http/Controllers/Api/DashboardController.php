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

            // ดึงข้อมูลวันนี้ + กะข้ามคืนจากเมื่อวาน
            $attendanceQuery = AttendanceLog::where(function ($q) use ($today, $yesterday) {
                    $q->where('date', $today)
                      ->orWhere(function ($q2) use ($yesterday) {
                          $q2->where('date', $yesterday)
                             ->whereNull('check_out');
                      });
                })
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            $presentToday = (clone $attendanceQuery)->pluck('emp_id')->unique()->count();
            $lateToday = (clone $attendanceQuery)->where('check_in_status', 'late')->count();
            $onTimeToday = (clone $attendanceQuery)->where('check_in_status', 'on_time')->count();
            $checkedOut = (clone $attendanceQuery)->whereNotNull('check_out')->pluck('emp_id')->unique()->count();
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

                $presentQ = AttendanceLog::where(function ($q) use ($today, $yesterday) {
                        $q->where('date', $today)
                          ->orWhere(function ($q2) use ($yesterday) {
                              $q2->where('date', $yesterday)
                                 ->whereNull('check_out');
                          });
                    })
                    ->whereHas('employee', function ($q) use ($company) {
                        $q->where('company_id', $company->id)->where('is_active', true);
                    });
                $present = (clone $presentQ)->pluck('emp_id')->unique()->count();
                $late = (clone $presentQ)->where('check_in_status', 'late')->count();

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

            // ดึงข้อมูลวันนี้ + กะข้ามคืนจากเมื่อวาน (ที่ยังไม่ได้เช็คเอาท์)
            $query = AttendanceLog::where(function ($q) use ($today, $yesterday) {
                    $q->where('date', $today)
                      ->orWhere(function ($q2) use ($yesterday) {
                          $q2->where('date', $yesterday)
                             ->whereNull('check_out');
                      });
                })
                ->with(['employee', 'employee.company'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->orderBy('round_no', 'asc')
                ->orderBy('check_in', 'desc');

            $logs = $query->get();

            // Group by employee, combine rounds
            $grouped = $logs->groupBy('emp_id');
            $records = $grouped->map(function ($empLogs) {
                $first = $empLogs->first();
                $employee = $first->employee;
                if (!$employee) return null;
                $logDate = $first->date instanceof Carbon ? $first->date->toDateString() : $first->date;

                $firstIn = AttendanceHelper::getFirstCheckIn($employee->id, $logDate);
                $lastOut = AttendanceHelper::getLastCheckOut($employee->id, $logDate);
                $workedHours = AttendanceHelper::calculateWorkedHours($employee->id, $logDate);
                $hasLate = $empLogs->contains('check_in_status', 'late') ||
                           $empLogs->contains('original_status', 'late');
                $lateMinutes = $empLogs->min('late_minutes') ?? 0;

                $checkInFormatted = $firstIn
                    ? Carbon::parse($firstIn)->setTimezone('Asia/Bangkok')->format('H:i')
                    : '-';
                $checkOutFormatted = $lastOut
                    ? Carbon::parse($lastOut)->setTimezone('Asia/Bangkok')->format('H:i')
                    : '-';

                $resolved = ShiftResolver::resolve($employee, $logDate);

                return [
                    'id' => $first->id,
                    'employee_name' => $employee->name ?? '-',
                    'employee_code' => $employee->employee_code ?? '-',
                    'company_name' => $employee->company->name ?? '-',
                    'company_code' => $employee->company->code_prefix ?? '-',
                    'date' => $first->date,
                    'check_in' => $checkInFormatted,
                    'check_out' => $checkOutFormatted,
                    'original_status' => $hasLate ? 'late' : 'on_time',
                    'final_status' => $hasLate ? 'late' : 'on_time',
                    'late_minutes' => $lateMinutes,
                    'scan_type' => $first->scan_type,
                    'is_late' => $hasLate,
                    'has_forced_leave' => method_exists($first, 'lateForcedLeave') ? $first->lateForcedLeave()->exists() : false,
                    'work_hours_display' => $workedHours !== null ? AttendanceCalculator::formatMinutes((int) ($workedHours * 60)) : '-',
                    'shift_code' => $resolved['shift_code'],
                    'shift_time' => ($resolved['start_time'] && $resolved['end_time'])
                        ? $resolved['start_time'] . '-' . $resolved['end_time']
                        : '-',
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
