<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $request->get('company_id');
            $month = $request->get('month', now()->format('Y-m'));

            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $query = Employee::where('is_active', true)
                ->with(['workShifts' => function ($q) use ($startDate, $endDate) {
                    $q->where(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNull('start_date')
                            ->orWhere(function ($q3) use ($startDate, $endDate) {
                                $q3->where('start_date', '<=', $endDate)
                                    ->where(function ($q4) use ($startDate, $endDate) {
                                        $q4->whereNull('end_date')
                                            ->orWhere('end_date', '>=', $startDate);
                                    });
                            });
                    });
                }, 'company']);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $employees = $query->get()->map(function ($emp) {
                $currentShift = $emp->workShifts->first();
                return [
                    'id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'name' => $emp->name,
                    'company_id' => $emp->company_id,
                    'company_name' => $emp->company->name ?? '-',
                    'current_shift' => $currentShift ? [
                        'id' => $currentShift->id,
                        'group_number' => $currentShift->group_number,
                        'start_time' => $currentShift->start_time ? $currentShift->start_time->format('H:i') : '',
                        'end_time' => $currentShift->end_time ? $currentShift->end_time->format('H:i') : '',
                        'is_overnight' => $currentShift->is_overnight,
                        'start_date' => $currentShift->pivot->start_date,
                        'end_date' => $currentShift->pivot->end_date,
                    ] : null,
                ];
            });

            $shifts = WorkShift::orderBy('group_number')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'employees' => $employees,
                    'shifts' => $shifts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function assign(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_ids' => 'required|array',
                'shift_id' => 'required|exists:work_shifts,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            foreach ($validated['employee_ids'] as $empId) {
                DB::table('employee_shifts')->where('employee_id', $empId)->delete();

                DB::table('employee_shifts')->insert([
                    'employee_id' => $empId,
                    'work_shift_id' => $validated['shift_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'มอบหมายกะเรียบร้อย (' . count($validated['employee_ids']) . ' คน)',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function remove(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_ids' => 'required|array',
            ]);

            DB::table('employee_shifts')
                ->whereIn('employee_id', $validated['employee_ids'])
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'ลบกะเรียบร้อย',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calendar view: Get all shift_schedules for a month
     */
    public function calendar(Request $request): JsonResponse
    {
        try {
            $month = $request->get('month', now()->format('Y-m'));
            $companyId = $request->get('company_id');
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $query = Employee::where('is_active', true)
                ->with('company');

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $employees = $query->orderBy('employee_code')->get();

            // Get shift schedules for this month
            $schedules = DB::table('shift_schedules')
                ->whereBetween('work_date', [$startDate, $endDate])
                ->when($companyId, function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->get()
                ->keyBy(function ($s) {
                    return $s->emp_id . '_' . $s->work_date;
                });

            // Get actual attendance
            $empIds = $employees->pluck('id')->toArray();
            $attendance = DB::table('attendance_logs')
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('emp_id', $empIds)
                ->where('status', '!=', 'holiday')
                ->get()
                ->groupBy('emp_id');

            // Get shifts
            $shifts = WorkShift::orderBy('group_number')->get();

            // Build calendar data
            $calendarData = $employees->map(function ($emp) use ($schedules, $attendance, $startDate, $endDate) {
                $days = [];
                $current = strtotime($startDate);
                $end = strtotime($endDate);
                $assignedDays = 0;

                while ($current <= $end) {
                    $dateStr = date('Y-m-d', $current);
                    $schedule = $schedules->get($emp->id . '_' . $dateStr);

                    $dayData = [
                        'date' => $dateStr,
                        'day_of_week' => date('w', $current),
                        'shift_code' => $schedule ? $schedule->shift_code : null,
                        'day_type' => $schedule ? $schedule->day_type : null,
                        'is_holiday' => in_array(date('w', $current), [0, 6]),
                    ];

                    if ($schedule && $schedule->day_type === 'working') {
                        $assignedDays++;
                    }

                    $days[] = $dayData;
                    $current = strtotime('+1 day', $current);
                }

                // Calculate actual attendance
                $empAttendance = $attendance->get($emp->id, collect());
                $actualDays = $empAttendance->pluck('date')->unique()->count();
                $totalMinutes = 0;
                $grouped = $empAttendance->groupBy('date');
                foreach ($grouped as $dayLogs) {
                    $first = $dayLogs->first();
                    $last = $dayLogs->last();
                    if ($first->check_in && $last->check_out) {
                        $in = strtotime($first->check_in);
                        $out = strtotime($last->check_out);
                        if ($out > $in) {
                            $totalMinutes += ($out - $in) / 60;
                        }
                    }
                }

                return [
                    'id' => $emp->id,
                    'employee_code' => $emp->employee_code,
                    'name' => $emp->name,
                    'company_id' => $emp->company_id,
                    'company_name' => $emp->company->name ?? '-',
                    'division' => $emp->division,
                    'department' => $emp->department,
                    'days' => $days,
                    'assigned_days' => $assignedDays,
                    'actual_days' => $actualDays,
                    'actual_hours' => round($totalMinutes / 60, 1),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'employees' => $calendarData,
                    'shifts' => $shifts,
                    'month' => $month,
                    'days_in_month' => (int) date('t', strtotime($startDate)),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a single day's shift for an employee
     */
    public function updateDay(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emp_id' => 'required|exists:employees,id',
                'work_date' => 'required|date',
                'shift_code' => 'nullable|string',
                'day_type' => 'required|in:working,holiday,day_off',
            ]);

            $employee = Employee::findOrFail($validated['emp_id']);

            if ($validated['day_type'] === 'working' && empty($validated['shift_code'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'ต้องเลือก shift_code สำหรับวันทำงาน',
                ], 422);
            }

            if ($validated['day_type'] === 'day_off') {
                DB::table('shift_schedules')
                    ->where('emp_id', $validated['emp_id'])
                    ->where('work_date', $validated['work_date'])
                    ->delete();
            } else {
                DB::table('shift_schedules')->updateOrInsert(
                    ['emp_id' => $validated['emp_id'], 'work_date' => $validated['work_date']],
                    [
                        'company_id' => $employee->company_id,
                        'shift_code' => $validated['shift_code'] ?? 'WC0002',
                        'day_type' => $validated['day_type'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'อัปเดตกะเรียบร้อย',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update: save multiple days at once
     */
    public function bulkUpdateDay(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'updates' => 'required|array',
                'updates.*.emp_id' => 'required|exists:employees,id',
                'updates.*.work_date' => 'required|date',
                'updates.*.shift_code' => 'nullable|string',
                'updates.*.day_type' => 'required|in:working,holiday,day_off',
            ]);

            $count = 0;
            foreach ($validated['updates'] as $update) {
                $employee = Employee::find($update['emp_id']);
                if (!$employee) continue;

                if ($update['day_type'] === 'day_off') {
                    // Delete the record for day_off
                    DB::table('shift_schedules')
                        ->where('emp_id', $update['emp_id'])
                        ->where('work_date', $update['work_date'])
                        ->delete();
                } else {
                    DB::table('shift_schedules')->updateOrInsert(
                        ['emp_id' => $update['emp_id'], 'work_date' => $update['work_date']],
                        [
                            'company_id' => $employee->company_id,
                            'shift_code' => $update['shift_code'] ?? 'WC0002',
                            'day_type' => $update['day_type'],
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
                $count++;
            }

            return response()->json([
                'success' => true,
                'message' => "อัปเดต {$count} วันเรียบร้อย",
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Summary for payroll review
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $month = $request->get('month', now()->format('Y-m'));
            $companyId = $request->get('company_id');
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $schedules = DB::table('shift_schedules')
                ->whereBetween('work_date', [$startDate, $endDate])
                ->when($companyId, function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })
                ->get()
                ->groupBy('emp_id');

            $empIds = $schedules->keys()->toArray();
            if (empty($empIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $employees = Employee::whereIn('id', $empIds)
                ->with('company')
                ->get()
                ->keyBy('id');

            // Get actual attendance
            $attendance = DB::table('attendance_logs')
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('emp_id', $empIds)
                ->where('status', '!=', 'holiday')
                ->get()
                ->groupBy('emp_id');

            $result = [];
            foreach ($schedules as $empId => $days) {
                $emp = $employees->get($empId);
                if (!$emp) continue;

                $workingDays = $days->where('day_type', 'working')->count();
                $empAttendance = $attendance->get($empId, collect());
                $actualDays = $empAttendance->pluck('date')->unique()->count();

                // Calculate actual hours
                $totalMinutes = 0;
                $grouped = $empAttendance->groupBy('date');
                foreach ($grouped as $dayLogs) {
                    $first = $dayLogs->first();
                    $last = $dayLogs->last();
                    if ($first->check_in && $last->check_out) {
                        $in = strtotime($first->check_in);
                        $out = strtotime($last->check_out);
                        $totalMinutes += ($out - $in) / 60;
                    }
                }

                $result[] = [
                    'emp_id' => $empId,
                    'employee_code' => $emp->employee_code,
                    'name' => $emp->name,
                    'company_name' => $emp->company->name ?? '-',
                    'division' => $emp->division,
                    'department' => $emp->department,
                    'scheduled_days' => $workingDays,
                    'actual_days' => $actualDays,
                    'diff_days' => $actualDays - $workingDays,
                    'actual_hours' => round($totalMinutes / 60, 1),
                    'status' => $actualDays >= $workingDays ? 'ok' : 'short',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
