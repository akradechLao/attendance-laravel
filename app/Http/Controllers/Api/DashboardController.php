<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Company;
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
                ->orderBy('check_in', 'desc');

            $records = $query->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'employee_name' => $log->employee->name ?? '-',
                    'employee_code' => $log->employee->employee_code ?? '-',
                    'company_name' => $log->employee->company->name ?? '-',
                    'company_code' => $log->employee->company->code_prefix ?? '-',
                    'check_in' => $log->check_in,
                    'check_out' => $log->check_out,
                    'check_in_status' => $log->check_in_status,
                    'scan_type' => $log->scan_type,
                    'is_late' => $log->check_in_status === 'late',
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
