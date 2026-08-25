<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function attendance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $query = AttendanceLog::whereBetween('check_in', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $logs = $query->with('employee.company')
                ->orderBy('check_in', 'desc')
                ->get();

            $totalRecords = $logs->count();
            $lateCount = $logs->where('status', 'late')->count();
            $onTimeCount = $logs->where('status', 'on_time')->count();

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $totalWorkingDays = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (!$date->isWeekend()) {
                    $totalWorkingDays++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $logs,
                    'summary' => [
                        'total_days' => $totalWorkingDays,
                        'on_time' => $onTimeCount,
                        'late' => $lateCount,
                        'absent' => max(0, $totalWorkingDays - $totalRecords),
                        'total_records' => $totalRecords,
                    ],
                ],
                'message' => 'Attendance report retrieved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve attendance report: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function monthly(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020',
            ]);

            $startDate = Carbon::create($request->year, $request->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $totalEmployees = Employee::where('company_id', $request->company_id)
                ->where('is_active', true)
                ->count();

            $attendance = AttendanceLog::where('company_id', $request->company_id)
                ->whereBetween('check_in', [$startDate, $endDate])
                ->get();

            $daysInMonth = $startDate->daysInMonth;
            $workingDays = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (!$date->isWeekend()) {
                    $workingDays++;
                }
            }

            $employeesWithAttendance = $attendance->pluck('employee_id')->unique()->count();
            $lateCount = $attendance->where('status', 'late')->count();
            $onTimeCount = $attendance->where('status', 'on_time')->count();
            $absentCount = ($totalEmployees * $workingDays) - $attendance->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_employees' => $totalEmployees,
                    'working_days' => $workingDays,
                    'employees_present' => $employeesWithAttendance,
                    'late_count' => $lateCount,
                    'on_time_count' => $onTimeCount,
                    'absent_count' => max(0, $absentCount),
                    'total_records' => $attendance->count(),
                ],
                'message' => 'Monthly report retrieved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve monthly report: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function employee(Request $request, $id): JsonResponse
    {
        try {
            $employee = Employee::with('company')->findOrFail($id);

            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $logs = AttendanceLog::where('employee_id', $id)
                ->whereBetween('check_in', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ])
                ->orderBy('check_in', 'desc')
                ->get();

            $totalDays = $logs->count();
            $lateCount = $logs->where('status', 'late')->count();
            $onTimeCount = $logs->where('status', 'on_time')->count();

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $totalWorkingDays = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (!$date->isWeekend()) {
                    $totalWorkingDays++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => $employee,
                    'attendance' => $logs,
                    'summary' => [
                        'total_working_days' => $totalWorkingDays,
                        'days_present' => $totalDays,
                        'days_absent' => max(0, $totalWorkingDays - $totalDays),
                        'late_count' => $lateCount,
                        'on_time_count' => $onTimeCount,
                    ],
                ],
                'message' => 'Employee report retrieved successfully.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Employee not found.',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve employee report: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportAttendance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $query = AttendanceLog::whereBetween('check_in', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $logs = $query->with('employee.company')
                ->orderBy('check_in', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to export attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function leave(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $query = LeaveRequest::whereBetween('start_date', [
                $request->start_date,
                $request->end_date,
            ]);

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $leaves = $query->with('employee.company', 'leaveType')
                ->orderBy('start_date', 'desc')
                ->get();

            $statusCounts = $leaves->groupBy('status')->map(fn($items) => $items->count());

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $leaves,
                    'summary' => [
                        'total' => $leaves->count(),
                        'approved' => $statusCounts->get('approved', 0),
                        'pending' => $statusCounts->get('pending', 0),
                        'rejected' => $statusCounts->get('rejected', 0),
                    ],
                ],
                'message' => 'Leave report retrieved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve leave report: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ot(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $query = OtRequest::whereBetween('date', [
                $request->start_date,
                $request->end_date,
            ]);

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            $ots = $query->with('employee.company')
                ->orderBy('date', 'desc')
                ->get();

            $statusCounts = $ots->groupBy('status')->map(fn($items) => $items->count());

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $ots,
                    'summary' => [
                        'total' => $ots->count(),
                        'approved' => $statusCounts->get('approved', 0),
                        'pending' => $statusCounts->get('pending', 0),
                        'rejected' => $statusCounts->get('rejected', 0),
                    ],
                ],
                'message' => 'OT report retrieved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve OT report: ' . $e->getMessage(),
            ], 500);
        }
    }
}
