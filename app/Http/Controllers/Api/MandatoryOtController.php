<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MandatoryOtAssignment;
use App\Models\Employee;
use App\Models\CompanyHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MandatoryOtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', now()->toDateString());
            $companyId = $request->get('company_id');

            $query = MandatoryOtAssignment::with(['employee', 'company'])
                ->where('ot_date', $date);

            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            $assignments = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $assignments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emp_id' => 'required|exists:employees,id',
                'ot_date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'reason' => 'nullable|string|max:500',
            ]);

            $employee = Employee::findOrFail($validated['emp_id']);
            $validated['company_id'] = $employee->company_id;
            $validated['assigned_by'] = $request->user()->username ?? 'Admin';

            $existing = MandatoryOtAssignment::where('emp_id', $validated['emp_id'])
                ->where('ot_date', $validated['ot_date'])
                ->first();

            if ($existing) {
                $existing->update($validated);
                $assignment = $existing;
            } else {
                $assignment = MandatoryOtAssignment::create($validated);
            }

            return response()->json([
                'success' => true,
                'data' => $assignment->load('employee'),
                'message' => 'มอบหมาย OT บังคับเรียบร้อย',
            ], 201);
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

    public function destroy($id): JsonResponse
    {
        try {
            $assignment = MandatoryOtAssignment::findOrFail($id);
            $assignment->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'ยกเลิก OT บังคับเรียบร้อย',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emp_ids' => 'required|array|min:1',
                'emp_ids.*' => 'exists:employees,id',
                'ot_date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'reason' => 'nullable|string|max:500',
            ]);

            $assignedBy = $request->user()->username ?? 'Admin';
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($validated['emp_ids'] as $empId) {
                $employee = Employee::find($empId);
                if (!$employee) { $skipped++; continue; }

                $existing = MandatoryOtAssignment::where('emp_id', $empId)
                    ->where('ot_date', $validated['ot_date'])
                    ->first();

                $data = [
                    'company_id' => $employee->company_id,
                    'emp_id' => $empId,
                    'ot_date' => $validated['ot_date'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'reason' => $validated['reason'] ?? null,
                    'assigned_by' => $assignedBy,
                    'status' => 'assigned',
                ];

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    MandatoryOtAssignment::create($data);
                    $created++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "มอบหมาย OT บังคับ {$created} ราย (อัพเดท {$updated} ราย, ข้าม {$skipped} ราย)",
                'data' => compact('created', 'updated', 'skipped'),
            ], 201);
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

    public function autoPreview(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emp_ids' => 'required|array|min:1',
                'emp_ids.*' => 'exists:employees,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'skip_sunday' => 'boolean',
                'skip_holiday' => 'boolean',
            ]);

            $skipSunday = $validated['skip_sunday'] ?? true;
            $skipHoliday = $validated['skip_holiday'] ?? true;

            $employees = Employee::whereIn('id', $validated['emp_ids'])->get();
            $companyIds = $employees->pluck('company_id')->unique()->toArray();

            $holidays = [];
            if ($skipHoliday) {
                $holidays = CompanyHoliday::whereIn('company_id', $companyIds)
                    ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
                    ->pluck('date')
                    ->map(fn($d) => $d->format('Y-m-d'))
                    ->toArray();
            }

            $datesToCreate = [];
            $datesSkipped = [];
            $current = strtotime($validated['start_date']);
            $end = strtotime($validated['end_date']);

            while ($current <= $end) {
                $dateStr = date('Y-m-d', $current);
                $dayOfWeek = date('w', $current);

                if ($skipSunday && $dayOfWeek == 0) {
                    $datesSkipped[] = ['date' => $dateStr, 'reason' => 'วันอาทิตย์'];
                } elseif (in_array($dateStr, $holidays)) {
                    $datesSkipped[] = ['date' => $dateStr, 'reason' => 'วันหยุดบริษัท'];
                } else {
                    $datesToCreate[] = $dateStr;
                }

                $current = strtotime('+1 day', $current);
            }

            $existingCount = MandatoryOtAssignment::whereIn('emp_id', $validated['emp_ids'])
                ->whereBetween('ot_date', [$validated['start_date'], $validated['end_date']])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'dates_to_create' => $datesToCreate,
                    'dates_skipped' => $datesSkipped,
                    'total_days' => count($datesToCreate),
                    'total_skipped' => count($datesSkipped),
                    'existing_count' => $existingCount,
                    'employee_count' => count($validated['emp_ids']),
                ],
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

    public function autoAssign(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emp_ids' => 'required|array|min:1',
                'emp_ids.*' => 'exists:employees,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'reason' => 'nullable|string|max:500',
                'skip_sunday' => 'boolean',
                'skip_holiday' => 'boolean',
                'selected_dates' => 'nullable|array',
            ]);

            $skipSunday = $validated['skip_sunday'] ?? true;
            $skipHoliday = $validated['skip_holiday'] ?? true;
            $assignedBy = $request->user()->username ?? 'Admin';

            $employees = Employee::whereIn('id', $validated['emp_ids'])->get();
            $companyIds = $employees->pluck('company_id')->unique()->toArray();

            $holidays = [];
            if ($skipHoliday) {
                $holidays = CompanyHoliday::whereIn('company_id', $companyIds)
                    ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
                    ->pluck('date')
                    ->map(fn($d) => $d->format('Y-m-d'))
                    ->toArray();
            }

            $datesToCreate = [];
            $current = strtotime($validated['start_date']);
            $end = strtotime($validated['end_date']);

            while ($current <= $end) {
                $dateStr = date('Y-m-d', $current);
                $dayOfWeek = date('w', $current);

                if ($skipSunday && $dayOfWeek == 0) {
                    $current = strtotime('+1 day', $current);
                    continue;
                }
                if (in_array($dateStr, $holidays)) {
                    $current = strtotime('+1 day', $current);
                    continue;
                }

                $datesToCreate[] = $dateStr;
                $current = strtotime('+1 day', $current);
            }

            $created = 0;
            $updated = 0;

            DB::beginTransaction();
            try {
                foreach ($validated['emp_ids'] as $empId) {
                    $employee = Employee::find($empId);
                    if (!$employee) continue;

                    foreach ($datesToCreate as $dateStr) {
                        $existing = MandatoryOtAssignment::where('emp_id', $empId)
                            ->where('ot_date', $dateStr)
                            ->first();

                        $data = [
                            'company_id' => $employee->company_id,
                            'emp_id' => $empId,
                            'ot_date' => $dateStr,
                            'start_time' => $validated['start_time'],
                            'end_time' => $validated['end_time'],
                            'reason' => $validated['reason'] ?? null,
                            'assigned_by' => $assignedBy,
                            'status' => 'assigned',
                        ];

                        if ($existing) {
                            $existing->update($data);
                            $updated++;
                        } else {
                            MandatoryOtAssignment::create($data);
                            $created++;
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => "มอบหมาย OT บังคับ {$created} วัน (อัพเดท {$updated} วัน, ข้าม วันอาทิตย์/หยุด " . count($validated['emp_ids']) . " คน)",
                'data' => compact('created', 'updated'),
            ], 201);
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
}
