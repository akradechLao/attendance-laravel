<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MandatoryOtAssignment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
