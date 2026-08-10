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
}
