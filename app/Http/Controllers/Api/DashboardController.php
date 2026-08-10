<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Company;
use App\Helpers\AttendanceCalculator;
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
            $today = Carbon::today()->toDateString();

            $employeeQuery = Employee::where('is_active', true);
            if ($companyId) {
                $employeeQuery->where('company_id', $companyId);
            }
            $totalEmployees = $employeeQuery->count();

            $attendanceQuery = AttendanceLog::where('date', $today)
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            $presentToday = (clone $attendanceQuery)->pluck('emp_id')->unique()->count();
            $lateToday = (clone $attendanceQuery)->where('check_in_status', 'late')->count();
            $onTimeToday = (clone $attendanceQuery)->where('check_in_status', 'on_time')->count();
            $checkedOut = (clone $attendanceQuery)->whereNotNull('check_out')->count();
            $absentToday = max(0, $totalEmployees - $presentToday);

            $monthStart = Carbon::now()->startOfMonth()->toDateString();
            $monthEnd = Carbon::now()->endOfMonth()->toDateString();

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

            $companies = Company::all();
            $companyStats = [];
            foreach ($companies as $company) {
                $totalQ = Employee::where('company_id', $company->id)->where('is_active', true);
                $total = $totalQ->count();

                $presentQ = AttendanceLog::where('date', $today)
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
                    ],
                    'monthly' => [
                        'late' => $monthlyLate,
                        'on_time' => $monthlyOnTime,
                        'total_records' => $monthlyAttendance->count(),
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
            $companyId = $request->get('company_id');

            $query = AttendanceLog::where('date', $today)
                ->with(['employee', 'employee.company'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                })
                ->orderBy('round_no', 'asc')
                ->orderBy('check_in', 'desc');

            $records = $query->get()->map(function ($log) {
                $checkIn = $log->check_in;
                $checkOut = $log->check_out;

                $checkInRaw = null;
                $checkOutRaw = null;

                if ($checkIn instanceof Carbon) {
                    $checkInRaw = $checkIn->copy();
                    $checkIn = $checkIn->format('H:i');
                } elseif (is_string($checkIn) && str_contains($checkIn, 'T')) {
                    $checkInRaw = Carbon::parse($checkIn)->setTimezone('Asia/Bangkok');
                    $checkIn = $checkInRaw->format('H:i');
                }
                if ($checkOut instanceof Carbon) {
                    $checkOutRaw = $checkOut->copy();
                    $checkOut = $checkOut->format('H:i');
                } elseif (is_string($checkOut) && str_contains($checkOut, 'T')) {
                    $checkOutRaw = Carbon::parse($checkOut)->setTimezone('Asia/Bangkok');
                    $checkOut = $checkOutRaw->format('H:i');
                }

                $workMinutes = 0;
                $workHoursDisplay = '-';
                if ($checkInRaw && $checkOutRaw) {
                    $workMinutes = AttendanceCalculator::calculateWorkMinutes($checkInRaw, $checkOutRaw);
                    $workHoursDisplay = AttendanceCalculator::formatMinutes($workMinutes);
                }

                $employee = $log->employee;
                $shift = $employee->workShifts()->where(function ($q) {
                    $q->whereNull('start_date')
                        ->orWhere('start_date', '<=', now()->toDateString());
                })->where(function ($q) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now()->toDateString());
                })->first();

                return [
                    'id' => $log->id,
                    'employee_name' => $employee->name ?? '-',
                    'employee_code' => $employee->employee_code ?? '-',
                    'company_name' => $employee->company->name ?? '-',
                    'company_code' => $employee->company->code_prefix ?? '-',
                    'date' => $log->date,
                    'round_no' => $log->round_no ?? 1,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'check_in_status' => $log->check_in_status,
                    'scan_type' => $log->scan_type,
                    'is_late' => $log->check_in_status === 'late',
                    'work_minutes' => $workMinutes,
                    'work_hours_display' => $workHoursDisplay,
                    'shift_group' => $shift ? $shift->group_number : null,
                    'shift_time' => $shift ? ($shift->start_time->format('H:i') . '-' . $shift->end_time->format('H:i')) : '-',
                ];
            });

            $present = $records->pluck('employee_name')->unique()->count();

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
