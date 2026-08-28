<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AttendanceHelper;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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
                ->orderBy('date', 'desc')
                ->orderBy('check_in', 'asc')
                ->get();

            // Group by date + employee, combine rounds
            $grouped = $logs->groupBy(fn($log) => $log->date . '_' . $log->emp_id);
            $combined = $grouped->map(function ($group) {
                $first = $group->first();
                $empId = $first->emp_id;
                $dateStr = \Carbon\Carbon::parse($first->date)->format('Y-m-d');
                $firstIn = AttendanceHelper::getFirstCheckIn($empId, $dateStr);
                $lastOut = AttendanceHelper::getLastCheckOut($empId, $dateStr);
                $workedHours = AttendanceHelper::calculateWorkedHours($empId, $dateStr);
                $hasLate = $group->contains('check_in_status', 'late');
                $lateMinutes = $group->min('late_minutes') ?? 0;

                $first->check_in = $firstIn;
                $first->check_out = $lastOut;
                $first->check_in_status = $hasLate ? 'late' : 'on_time';
                $first->late_minutes = $lateMinutes;
                $first->calculated_work_hours = $workedHours;

                return $first;
            })->values();

            $totalRecords = $combined->count();
            $lateCount = $combined->where('check_in_status', 'late')->count();
            $onTimeCount = $combined->where('check_in_status', 'on_time')->count();

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
                    'records' => $combined,
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

            $attendance = AttendanceLog::whereHas('employee', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                })
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
                ->orderBy('date', 'desc')
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

    public function exportAttendancePdf(Request $request)
    {
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

        $logs = $query->with('employee.company')->orderBy('check_in', 'asc')->get();

        $html = $this->buildAttendancePdfHtml($logs, $request->start_date, $request->end_date);

        $pdf = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        return $pdf->download('attendance-report-' . $request->start_date . '.pdf');
    }

    public function exportLeavePdf(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $query = LeaveRequest::whereBetween('start_date', [$request->start_date, $request->end_date]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $leaves = $query->with('employee.company', 'leaveType')->orderBy('start_date', 'desc')->get();

        $html = $this->buildLeavePdfHtml($leaves, $request->start_date, $request->end_date);

        $pdf = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        return $pdf->download('leave-report-' . $request->start_date . '.pdf');
    }

    public function exportOtPdf(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $query = OtRequest::whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $ots = $query->with('employee.company')->orderBy('date', 'desc')->get();

        $html = $this->buildOtPdfHtml($ots, $request->start_date, $request->end_date);

        $pdf = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        return $pdf->download('ot-report-' . $request->start_date . '.pdf');
    }

    private function buildAttendancePdfHtml($logs, $startDate, $endDate): string
    {
        $rows = '';
        foreach ($logs as $i => $log) {
            $empCode = $log->employee?->employee_code ?? '-';
            $empName = $log->employee?->name ?? '-';
            $company = $log->employee?->company?->name ?? '-';
            $checkIn = $log->check_in ? Carbon::parse($log->check_in)->setTimezone('Asia/Bangkok')->format('H:i') : '-';
            $checkOut = $log->check_out ? Carbon::parse($log->check_out)->setTimezone('Asia/Bangkok')->format('H:i') : '-';
            $status = match($log->check_in_status) {
                'late' => 'สาย',
                'on_time' => 'ปกติ',
                default => '-',
            };
            $workHours = '-';
            if (isset($log->calculated_work_hours) && $log->calculated_work_hours !== null) {
                $totalMins = (int) ($log->calculated_work_hours * 60);
                $h = intdiv($totalMins, 60);
                $m = $totalMins % 60;
                $workHours = $h . 'ชม.' . ($m > 0 ? $m . 'น.' : '');
            } elseif ($log->check_in && $log->check_out) {
                $in = Carbon::parse($log->check_in);
                $out = Carbon::parse($log->check_out);
                $mins = $in->diffInMinutes($out) - 60;
                $mins = max(0, $mins);
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $workHours = $h . 'ชม.' . ($m > 0 ? $m . 'น.' : '');
            }
            $dateStr = $log->date ? Carbon::parse($log->date)->format('d/m/Y') : '-';
            $rows .= "<tr>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$dateStr}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$empCode}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$empName}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$company}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$checkIn}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$checkOut}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$workHours}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$status}</td>
            </tr>";
        }

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
            body { font-family: 'Thai', sans-serif; }
        </style></head><body>
            <h2 style='text-align:center'>รายงานเข้างาน</h2>
            <p style='text-align:center'>วันที่ {$startDate} - {$endDate}</p>
            <p style='text-align:center'>รวม {$logs->count()} รายการ</p>
            <table style='width:100%;border-collapse:collapse'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>วันที่</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>รหัส</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ชื่อ</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>บริษัท</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>เช็คอิน</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>เช็คเอาท์</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ชั่วโมงทำงาน</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>สถานะ</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table></body></html>";
    }

    private function buildLeavePdfHtml($leaves, $startDate, $endDate): string
    {
        $rows = '';
        foreach ($leaves as $i => $leave) {
            $empName = $leave->employee?->name ?? '-';
            $company = $leave->employee?->company?->name ?? '-';
            $type = $leave->leaveType?->name ?? '-';
            $status = match($leave->status) {
                'approved' => '<span style="color:#16a34a;font-weight:bold">อนุมัติ</span>',
                'pending' => '<span style="color:#d97706;font-weight:bold">รออนุมัติ</span>',
                'rejected' => '<span style="color:#dc2626;font-weight:bold">ปฏิเสธ</span>',
                default => '-',
            };
            $rows .= "<tr>
                <td style='border:1px solid #ddd;padding:6px'>{$i}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$empName}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$company}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$type}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$leave->start_date}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$leave->end_date}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$leave->total_days}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$status}</td>
            </tr>";
        }

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
            body { font-family: 'Thai', sans-serif; }
        </style></head><body>
            <h2 style='text-align:center'>รายงานการลา</h2>
            <p style='text-align:center'>วันที่ {$startDate} - {$endDate}</p>
            <p style='text-align:center'>รวม {$leaves->count()} รายการ</p>
            <table style='width:100%;border-collapse:collapse'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>#</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ชื่อ</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>บริษัท</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ประเภทลา</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>วันเริ่ม</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>วันสิ้นสุด</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>จำนวนวัน</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>สถานะ</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table></body></html>";
    }

    private function buildOtPdfHtml($ots, $startDate, $endDate): string
    {
        $rows = '';
        foreach ($ots as $i => $ot) {
            $empName = $ot->employee?->name ?? '-';
            $company = $ot->employee?->company?->name ?? '-';
            $status = match($ot->status) {
                'approved' => '<span style="color:#16a34a;font-weight:bold">อนุมัติ</span>',
                'pending' => '<span style="color:#d97706;font-weight:bold">รออนุมัติ</span>',
                'rejected' => '<span style="color:#dc2626;font-weight:bold">ปฏิเสธ</span>',
                default => '-',
            };
            $rows .= "<tr>
                <td style='border:1px solid #ddd;padding:6px'>{$i}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$empName}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$company}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$ot->date}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$ot->start_time} - {$ot->end_time}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$ot->total_hours}</td>
                <td style='border:1px solid #ddd;padding:6px'>{$status}</td>
            </tr>";
        }

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
            body { font-family: 'Thai', sans-serif; }
        </style></head><body>
            <h2 style='text-align:center'>รายงาน OT</h2>
            <p style='text-align:center'>วันที่ {$startDate} - {$endDate}</p>
            <p style='text-align:center'>รวม {$ots->count()} รายการ</p>
            <table style='width:100%;border-collapse:collapse'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>#</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ชื่อ</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>บริษัท</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>วันที่</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>เวลา</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>ชั่วโมง</th>
                    <th style='border:1px solid #ddd;padding:4px;font-size:10px'>สถานะ</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table></body></html>";
    }
}
