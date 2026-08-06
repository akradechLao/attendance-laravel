<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');
            $today = Carbon::today();

            $query = Employee::where('is_active', true);
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
            $totalEmployees = $query->count();

            $attendanceQuery = AttendanceLog::whereDate('check_in', $today);
            if ($companyId) {
                $attendanceQuery->where('company_id', $companyId);
            }

            $todayAttendance = $attendanceQuery->get();
            $presentToday = $todayAttendance->pluck('employee_id')->unique()->count();
            $lateToday = $todayAttendance->where('status', 'late')->count();
            $onTimeToday = $todayAttendance->where('status', 'on_time')->count();
            $absentToday = max(0, $totalEmployees - $presentToday);

            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();

            $monthlyQuery = AttendanceLog::whereBetween('check_in', [$monthStart, $monthEnd]);
            if ($companyId) {
                $monthlyQuery->where('company_id', $companyId);
            }
            $monthlyAttendance = $monthlyQuery->get();
            $monthlyLate = $monthlyAttendance->where('status', 'late')->count();
            $monthlyOnTime = $monthlyAttendance->where('status', 'on_time')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'today' => [
                        'present' => $presentToday,
                        'late' => $lateToday,
                        'on_time' => $onTimeToday,
                        'absent' => $absentToday,
                    ],
                    'monthly' => [
                        'late' => $monthlyLate,
                        'on_time' => $monthlyOnTime,
                        'total_records' => $monthlyAttendance->count(),
                    ],
                ],
                'message' => 'Dashboard stats retrieved successfully.',
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
            $today = Carbon::today();
            $companyId = $request->get('company_id');

            $query = AttendanceLog::whereDate('check_in', $today)
                ->with('employee');

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $attendance = $query->get();

            $present = $attendance->pluck('employee_id')->unique()->count();
            $late = $attendance->where('status', 'late')->count();
            $onTime = $attendance->where('status', 'on_time')->count();

            $totalQuery = Employee::where('is_active', true);
            if ($companyId) {
                $totalQuery->where('company_id', $companyId);
            }
            $totalEmployees = $totalQuery->count();
            $absent = max(0, $totalEmployees - $present);

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $today->toDateString(),
                    'total_employees' => $totalEmployees,
                    'present' => $present,
                    'late' => $late,
                    'on_time' => $onTime,
                    'absent' => $absent,
                    'records' => $attendance,
                ],
                'message' => 'Today\'s attendance summary retrieved successfully.',
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
