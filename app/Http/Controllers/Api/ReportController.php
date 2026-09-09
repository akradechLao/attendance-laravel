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
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportController extends Controller
{
    private function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('division')) {
            $query->whereHas('employee', fn($q) => $q->where('division', $request->division));
        }
        if ($request->filled('department')) {
            $query->whereHas('employee', fn($q) => $q->where('department', $request->department));
        }
        if ($request->filled('emp_id')) {
            $query->where('emp_id', $request->emp_id);
        }
    }

    public function attendance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'division' => 'nullable|string',
                'department' => 'nullable|string',
                'emp_id' => 'nullable|exists:employees,id',
            ]);

            $query = AttendanceLog::whereBetween('date', [
                $request->start_date,
                $request->end_date,
            ]);

            $this->applyCommonFilters($query, $request);

            $logs = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix')
                ->orderBy('date', 'desc')
                ->orderBy('check_in', 'asc')
                ->get();

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
            $totalLateMinutes = (int) $combined->sum('late_minutes');

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
                        'total_late_minutes' => $totalLateMinutes,
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
            \Log::error('ReportController attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
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
                ->whereBetween('date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->get();

            $daysInMonth = $startDate->daysInMonth;
            $workingDays = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (!$date->isWeekend()) {
                    $workingDays++;
                }
            }

            $employeesWithAttendance = $attendance->pluck('employee_id')->unique()->count();
            $lateCount = $attendance->where('check_in_status', 'late')->count();
            $onTimeCount = $attendance->where('check_in_status', 'on_time')->count();
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
            \Log::error('ReportController monthly: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
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
                ->whereBetween('date', [
                    $request->start_date,
                    $request->end_date,
                ])
                ->orderBy('date', 'desc')
                ->orderBy('check_in', 'desc')
                ->get();

            $totalDays = $logs->count();
            $lateCount = $logs->where('check_in_status', 'late')->count();
            $onTimeCount = $logs->where('check_in_status', 'on_time')->count();

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
            \Log::error('ReportController employee: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
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
                'division' => 'nullable|string',
                'department' => 'nullable|string',
                'emp_id' => 'nullable|exists:employees,id',
            ]);

            $query = AttendanceLog::whereBetween('date', [
                $request->start_date,
                $request->end_date,
            ]);

            $this->applyCommonFilters($query, $request);

            $logs = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix')
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
            \Log::error('ReportController exportAttendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
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
                'division' => 'nullable|string',
                'department' => 'nullable|string',
                'emp_id' => 'nullable|exists:employees,id',
            ]);

            $query = LeaveRequest::whereBetween('start_date', [
                $request->start_date,
                $request->end_date,
            ]);

            $this->applyCommonFilters($query, $request);

            $leaves = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix', 'leaveType')
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
            \Log::error('ReportController leave: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
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
                'division' => 'nullable|string',
                'department' => 'nullable|string',
                'emp_id' => 'nullable|exists:employees,id',
            ]);

            $query = OtRequest::whereBetween('date', [
                $request->start_date,
                $request->end_date,
            ]);

            $this->applyCommonFilters($query, $request);

            $ots = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix')
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
            \Log::error('ReportController ot: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function exportAttendancePdf(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'division' => 'nullable|string',
            'department' => 'nullable|string',
            'emp_id' => 'nullable|exists:employees,id',
        ]);

        $query = AttendanceLog::whereBetween('date', [
            $request->start_date,
            $request->end_date,
        ]);

        $this->applyCommonFilters($query, $request);

        $logs = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix')->orderBy('date', 'desc')->orderBy('check_in', 'asc')->get();

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
            'division' => 'nullable|string',
            'department' => 'nullable|string',
            'emp_id' => 'nullable|exists:employees,id',
        ]);

        $query = LeaveRequest::whereBetween('start_date', [$request->start_date, $request->end_date]);

        $this->applyCommonFilters($query, $request);

        $leaves = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix', 'leaveType')->orderBy('start_date', 'desc')->get();

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
            'division' => 'nullable|string',
            'department' => 'nullable|string',
            'emp_id' => 'nullable|exists:employees,id',
        ]);

        $query = OtRequest::whereBetween('date', [$request->start_date, $request->end_date]);

        $this->applyCommonFilters($query, $request);

        $ots = $query->with('employee:id,id,employee_code,name,nickname,photo,company_id,position,department,division,has_ot,is_active,reports_to,supervisor_name,office_location_id', 'employee.company:id,name,code_prefix')->orderBy('date', 'desc')->get();

        $html = $this->buildOtPdfHtml($ots, $request->start_date, $request->end_date);

        $pdf = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        return $pdf->download('ot-report-' . $request->start_date . '.pdf');
    }

    public function exportAttendanceXls(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:attendance,leave,ot',
            'division' => 'nullable|string',
            'department' => 'nullable|string',
            'emp_id' => 'nullable|exists:employees,id',
        ]);

        $type = $request->type;

        if ($type === 'attendance') {
            $html = $this->buildAttendanceXlsHtml($request);
        } elseif ($type === 'leave') {
            $html = $this->buildLeaveXlsHtml($request);
        } else {
            $html = $this->buildOtXlsHtml($request);
        }

        $filename = "{$type}-report-{$request->start_date}.xls";

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Pragma', 'no-cache')
            ->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    }

    private function buildAttendanceXlsHtml(Request $request): string
    {
        $query = AttendanceLog::whereBetween('date', [$request->start_date, $request->end_date]);
        $this->applyCommonFilters($query, $request);

        $logs = $query->with('employee:id,id,employee_code,name,nickname,company_id,position,department,division', 'employee.company:id,name')
            ->orderBy('date', 'desc')
            ->orderBy('check_in', 'asc')
            ->get();

        $grouped = $logs->groupBy(fn($log) => $log->date . '_' . $log->emp_id);
        $combined = $grouped->map(function ($group) {
            $first = $group->first();
            $empId = $first->emp_id;
            $dateStr = Carbon::parse($first->date)->format('Y-m-d');
            $first->check_in = AttendanceHelper::getFirstCheckIn($empId, $dateStr);
            $first->check_out = AttendanceHelper::getLastCheckOut($empId, $dateStr);
            $first->calculated_work_hours = AttendanceHelper::calculateWorkedHours($empId, $dateStr);
            $first->check_in_status = $group->contains('check_in_status', 'late') ? 'late' : 'on_time';
            $first->late_minutes = $group->min('late_minutes') ?? 0;
            return $first;
        })->values();

        $rows = '';
        foreach ($combined as $log) {
            $empCode = htmlspecialchars($log->employee?->employee_code ?? '-');
            $empName = htmlspecialchars($log->employee?->name ?? '-');
            $company = htmlspecialchars($log->employee?->company?->name ?? '-');
            $division = htmlspecialchars($log->employee?->division ?? '-');
            $department = htmlspecialchars($log->employee?->department ?? '-');
            $dateStr = $log->date ? Carbon::parse($log->date)->format('d/m/Y') : '-';
            $checkIn = $log->check_in ? $this->fmtTime($log->check_in) : '-';
            $checkOut = $log->check_out ? $this->fmtTime($log->check_out) : '-';
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
            }
            $lateMin = (int) ($log->late_minutes ?? 0);
            $lateText = $lateMin > 0 ? $lateMin . ' นาที' : '-';

            $rows .= "<tr>
                <td style='mso-number-format:\"\\@\"'>{$dateStr}</td>
                <td style='mso-number-format:\"\\@\"'>{$empCode}</td>
                <td style='mso-number-format:\"\\@\"'>{$empName}</td>
                <td style='mso-number-format:\"\\@\"'>{$company}</td>
                <td style='mso-number-format:\"\\@\"'>{$division}</td>
                <td style='mso-number-format:\"\\@\"'>{$department}</td>
                <td style='mso-number-format:\"\\@\"'>{$checkIn}</td>
                <td style='mso-number-format:\"\\@\"'>{$checkOut}</td>
                <td style='mso-number-format:\"\\@\"'>{$workHours}</td>
                <td style='mso-number-format:\"\\@\"'>{$lateText}</td>
                <td style='mso-number-format:\"\\@\"'>{$status}</td>
            </tr>";
        }

        return $this->buildXlsWrapper("รายงานเข้างาน {$request->start_date} - {$request->end_date}", $rows,
            '<th>วันที่</th><th>รหัส</th><th>ชื่อ</th><th>บริษัท</th><th>ฝ่าย</th><th>แผนก</th><th>เช็คอิน</th><th>เช็คเอาท์</th><th>ชั่วโมงทำงาน</th><th>สาย (นาที)</th><th>สถานะ</th>');
    }

    private function buildLeaveXlsHtml(Request $request): string
    {
        $query = LeaveRequest::whereBetween('start_date', [$request->start_date, $request->end_date]);
        $this->applyCommonFilters($query, $request);

        $leaves = $query->with('employee:id,id,employee_code,name,company_id,department,division', 'employee.company:id,name', 'leaveType')
            ->orderBy('start_date', 'desc')
            ->get();

        $rows = '';
        $i = 1;
        foreach ($leaves as $leave) {
            $empCode = htmlspecialchars($leave->employee?->employee_code ?? '-');
            $empName = htmlspecialchars($leave->employee?->name ?? '-');
            $company = htmlspecialchars($leave->employee?->company?->name ?? '-');
            $division = htmlspecialchars($leave->employee?->division ?? '-');
            $department = htmlspecialchars($leave->employee?->department ?? '-');
            $type = htmlspecialchars($leave->leaveType?->name ?? '-');
            $status = match($leave->status) {
                'approved' => 'อนุมัติ',
                'pending' => 'รออนุมัติ',
                'rejected' => 'ปฏิเสธ',
                default => '-',
            };

            $rows .= "<tr>
                <td style='mso-number-format:\"\\@\"'>{$i}</td>
                <td style='mso-number-format:\"\\@\"'>{$empCode}</td>
                <td style='mso-number-format:\"\\@\"'>{$empName}</td>
                <td style='mso-number-format:\"\\@\"'>{$company}</td>
                <td style='mso-number-format:\"\\@\"'>{$division}</td>
                <td style='mso-number-format:\"\\@\"'>{$department}</td>
                <td style='mso-number-format:\"\\@\"'>{$type}</td>
                <td style='mso-number-format:\"\\@\"'>{$leave->start_date}</td>
                <td style='mso-number-format:\"\\@\"'>{$leave->end_date}</td>
                <td style='mso-number-format:\"\\@\"'>{$leave->total_days}</td>
                <td style='mso-number-format:\"\\@\"'>{$status}</td>
            </tr>";
            $i++;
        }

        return $this->buildXlsWrapper("รายงานการลา {$request->start_date} - {$request->end_date}", $rows,
            '<th>#</th><th>รหัส</th><th>ชื่อ</th><th>บริษัท</th><th>ฝ่าย</th><th>แผนก</th><th>ประเภทลา</th><th>วันเริ่ม</th><th>วันสิ้นสุด</th><th>จำนวนวัน</th><th>สถานะ</th>');
    }

    private function buildOtXlsHtml(Request $request): string
    {
        $query = OtRequest::whereBetween('date', [$request->start_date, $request->end_date]);
        $this->applyCommonFilters($query, $request);

        $ots = $query->with('employee:id,id,employee_code,name,company_id,department,division', 'employee.company:id,name')
            ->orderBy('date', 'desc')
            ->get();

        $rows = '';
        $i = 1;
        foreach ($ots as $ot) {
            $empCode = htmlspecialchars($ot->employee?->employee_code ?? '-');
            $empName = htmlspecialchars($ot->employee?->name ?? '-');
            $company = htmlspecialchars($ot->employee?->company?->name ?? '-');
            $division = htmlspecialchars($ot->employee?->division ?? '-');
            $department = htmlspecialchars($ot->employee?->department ?? '-');
            $status = match($ot->status) {
                'approved' => 'อนุมัติ',
                'pending' => 'รออนุมัติ',
                'rejected' => 'ปฏิเสธ',
                default => '-',
            };

            $rows .= "<tr>
                <td style='mso-number-format:\"\\@\"'>{$i}</td>
                <td style='mso-number-format:\"\\@\"'>{$empCode}</td>
                <td style='mso-number-format:\"\\@\"'>{$empName}</td>
                <td style='mso-number-format:\"\\@\"'>{$company}</td>
                <td style='mso-number-format:\"\\@\"'>{$division}</td>
                <td style='mso-number-format:\"\\@\"'>{$department}</td>
                <td style='mso-number-format:\"\\@\"'>{$ot->date}</td>
                <td style='mso-number-format:\"\\@\"'>{$ot->start_time} - {$ot->end_time}</td>
                <td style='mso-number-format:\"\\@\"'>{$ot->total_hours}</td>
                <td style='mso-number-format:\"\\@\"'>{$status}</td>
            </tr>";
            $i++;
        }

        return $this->buildXlsWrapper("รายงาน OT {$request->start_date} - {$request->end_date}", $rows,
            '<th>#</th><th>รหัส</th><th>ชื่อ</th><th>บริษัท</th><th>ฝ่าย</th><th>แผนก</th><th>วันที่</th><th>เวลา</th><th>ชั่วโมง</th><th>สถานะ</th>');
    }

    private function buildXlsWrapper(string $title, string $rows, string $headers): string
    {
        return "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
<head><meta charset='utf-8'>
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{$title}</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
<style>td,th{border:1px solid #999;padding:4px 8px;font-size:11px}th{background:#f3f4f6;font-weight:bold}</style>
</head><body>
<div style='font-size:16px;font-weight:bold;margin-bottom:10px'>{$title}</div>
<table border='1' cellpadding='0' cellspacing='0'>
<thead><tr>{$headers}</tr></thead>
<tbody>{$rows}</tbody>
</table></body></html>";
    }

    private function fmtTime($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }
        if (is_string($value)) {
            if (preg_match('/(\d{1,2}:\d{2})/', $value, $m)) {
                return $m[1];
            }
            return $value;
        }
        return '-';
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
            $lateMin = (int) ($log->late_minutes ?? 0);
            $lateText = $lateMin > 0 ? $lateMin . ' นาที' : '-';
            $rows .= "<tr>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$dateStr}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$empCode}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$empName}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$company}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$checkIn}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$checkOut}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$workHours}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$lateText}</td>
                <td style='border:1px solid #ddd;padding:4px;font-size:10px'>{$status}</td>
            </tr>";
        }

        $f = 'font-family:Thai,sans-serif';

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
        </style></head><body>
            <div style='text-align:center;font-size:18px;{$f}'>รายงานเข้างาน</div>
            <div style='text-align:center;{$f}'>วันที่ {$startDate} - {$endDate}</div>
            <div style='text-align:center;{$f}'>รวม {$logs->count()} รายการ</div>
            <table style='width:100%;border-collapse:collapse;{$f}'>
                <thead><tr style='background:#f3f4f6'>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>วันที่</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>รหัส</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ชื่อ</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>บริษัท</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>เช็คอิน</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>เช็คเอาท์</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ชั่วโมงทำงาน</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>สาย (นาที)</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>สถานะ</td>
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

        $f = 'font-family:Thai,sans-serif';

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
        </style></head><body>
            <div style='text-align:center;font-size:18px;{$f}'>รายงานการลา</div>
            <div style='text-align:center;{$f}'>วันที่ {$startDate} - {$endDate}</div>
            <div style='text-align:center;{$f}'>รวม {$leaves->count()} รายการ</div>
            <table style='width:100%;border-collapse:collapse;{$f}'>
                <thead><tr style='background:#f3f4f6'>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>#</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ชื่อ</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>บริษัท</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ประเภทลา</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>วันเริ่ม</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>วันสิ้นสุด</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>จำนวนวัน</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>สถานะ</td>
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

        $f = 'font-family:Thai,sans-serif';

        return "<html><head><meta charset='utf-8'><style>
            @font-face { font-family: 'Thai'; src: url('file://" . public_path('fonts/NotoSansThai-Regular.ttf') . "'); }
        </style></head><body>
            <div style='text-align:center;font-size:18px;{$f}'>รายงาน OT</div>
            <div style='text-align:center;{$f}'>วันที่ {$startDate} - {$endDate}</div>
            <div style='text-align:center;{$f}'>รวม {$ots->count()} รายการ</div>
            <table style='width:100%;border-collapse:collapse;{$f}'>
                <thead><tr style='background:#f3f4f6'>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>#</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ชื่อ</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>บริษัท</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>วันที่</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>เวลา</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>ชั่วโมง</td>
                    <td style='border:1px solid #ddd;padding:4px;font-size:10px;{$f}'>สถานะ</td>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table></body></html>";
    }
}
