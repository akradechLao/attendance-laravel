<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftRequest;
use App\Models\WorkShift;
use App\Models\Employee;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'work_shift_id' => 'required|exists:work_shifts,id',
                'request_type' => 'required|in:assign,modify,remove',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'new_start_time' => 'nullable|date_format:H:i',
                'new_end_time' => 'nullable|date_format:H:i|after:new_start_time',
                'reason' => 'nullable|string|max:500',
            ]);

            $employee = $request->user();

            if ($validated['request_type'] === 'modify' && (!$validated['new_start_time'] || !$validated['new_end_time'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณาระบุเวลาเข้า-ออกใหม่',
                ], 400);
            }

            if ($validated['request_type'] === 'remove') {
                $exists = DB::table('employee_shifts')
                    ->where('employee_id', $employee->id)
                    ->where('work_shift_id', $validated['work_shift_id'])
                    ->first();

                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ไม่มีกานี้ที่จะลบ',
                    ], 400);
                }
            }

            $shiftReq = ShiftRequest::create([
                'company_id' => $employee->company_id,
                'emp_id' => $employee->id,
                'work_shift_id' => $validated['work_shift_id'],
                'request_type' => $validated['request_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? $validated['start_date'],
                'new_start_time' => $validated['new_start_time'] ?? null,
                'new_end_time' => $validated['new_end_time'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'status' => 'pending',
            ]);

            $shiftReq->load('workShift');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $shiftReq->id,
                    'request_type' => $shiftReq->request_type,
                    'work_shift' => [
                        'id' => $shiftReq->workShift->id,
                        'group_number' => $shiftReq->workShift->group_number,
                        'start_time' => $shiftReq->workShift->start_time ? Carbon::parse($shiftReq->workShift->start_time)->format('H:i') : null,
                        'end_time' => $shiftReq->workShift->end_time ? Carbon::parse($shiftReq->workShift->end_time)->format('H:i') : null,
                    ],
                    'start_date' => $shiftReq->start_date->format('Y-m-d'),
                    'end_date' => $shiftReq->end_date->format('Y-m-d'),
                    'new_start_time' => $shiftReq->new_start_time,
                    'new_end_time' => $shiftReq->new_end_time,
                    'reason' => $shiftReq->reason,
                    'status' => $shiftReq->status,
                    'created_at' => $shiftReq->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i'),
                ],
                'message' => 'ส่งคำขอสำเร็จ',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'กรอกข้อมูลไม่ถูกต้อง',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function myRequests(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            $requests = ShiftRequest::with('workShift')
                ->where('emp_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'request_type' => $r->request_type,
                    'work_shift' => [
                        'id' => $r->workShift->id,
                        'group_number' => $r->workShift->group_number,
                        'start_time' => $r->workShift->start_time ? Carbon::parse($r->workShift->start_time)->format('H:i') : null,
                        'end_time' => $r->workShift->end_time ? Carbon::parse($r->workShift->end_time)->format('H:i') : null,
                    ],
                    'start_date' => $r->start_date->format('Y-m-d'),
                    'end_date' => $r->end_date->format('Y-m-d'),
                    'new_start_time' => $r->new_start_time,
                    'new_end_time' => $r->new_end_time,
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'supervisor_note' => $r->supervisor_note,
                    'created_at' => $r->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i'),
                ]);

            return response()->json([
                'success' => true,
                'data' => $requests,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function availableShifts(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            $shifts = WorkShift::orderBy('group_number')->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'group_number' => $s->group_number,
                    'start_time' => $s->start_time ? Carbon::parse($s->start_time)->format('H:i') : null,
                    'end_time' => $s->end_time ? Carbon::parse($s->end_time)->format('H:i') : null,
                    'work_hours' => $s->work_hours,
                ]);

            $currentShifts = DB::table('employee_shifts')
                ->where('employee_id', $employee->id)
                ->get()
                ->map(fn($s) => [
                    'work_shift_id' => $s->work_shift_id,
                    'start_date' => $s->start_date,
                    'end_date' => $s->end_date,
                    'override_start_time' => $s->override_start_time,
                    'override_end_time' => $s->override_end_time,
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'shifts' => $shifts,
                    'current_shifts' => $currentShifts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function teamRequests(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (method_exists($user, 'getAllSubordinateIds')) {
                $subordinateIds = $user->getAllSubordinateIds();
            } else {
                $subordinateIds = \App\Models\Employee::where('company_id', $user->company_id)->pluck('id')->toArray();
            }

            $requests = ShiftRequest::with(['employee', 'workShift'])
                ->whereIn('emp_id', $subordinateIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'request_type' => $r->request_type,
                    'employee' => [
                        'id' => $r->employee->id,
                        'employee_code' => $r->employee->employee_code,
                        'name' => $r->employee->name,
                    ],
                    'work_shift' => [
                        'id' => $r->workShift->id,
                        'group_number' => $r->workShift->group_number,
                        'start_time' => $r->workShift->start_time ? Carbon::parse($r->workShift->start_time)->format('H:i') : null,
                        'end_time' => $r->workShift->end_time ? Carbon::parse($r->workShift->end_time)->format('H:i') : null,
                    ],
                    'start_date' => $r->start_date->format('Y-m-d'),
                    'end_date' => $r->end_date->format('Y-m-d'),
                    'new_start_time' => $r->new_start_time,
                    'new_end_time' => $r->new_end_time,
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'created_at' => $r->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i'),
                ]);

            return response()->json([
                'success' => true,
                'data' => $requests,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $shiftReq = ShiftRequest::findOrFail($id);

            if ($shiftReq->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'คำขอนี้ได้รับการดำเนินการแล้ว',
                ], 400);
            }

            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, ['admin', 'super_admin'])) {
                if (!$user->isSubordinateOf($shiftReq->emp_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
                }
            }

            $validated = $request->validate([
                'supervisor_note' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();

            $shiftReq->update([
                'status' => 'approved',
                'supervisor_id' => $user->id,
                'supervisor_note' => $validated['supervisor_note'] ?? null,
            ]);

            $employeeId = $shiftReq->emp_id;
            $shiftId = $shiftReq->work_shift_id;
            $startDate = $shiftReq->start_date->format('Y-m-d');
            $endDate = $shiftReq->end_date ? $shiftReq->end_date->format('Y-m-d') : $startDate;

            if ($shiftReq->request_type === 'assign') {
                DB::table('employee_shifts')->where('employee_id', $employeeId)->delete();

                DB::table('employee_shifts')->insert([
                    'employee_id' => $employeeId,
                    'work_shift_id' => $shiftId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'override_start_time' => $shiftReq->new_start_time,
                    'override_end_time' => $shiftReq->new_end_time,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($shiftReq->request_type === 'modify') {
                DB::table('employee_shifts')
                    ->where('employee_id', $employeeId)
                    ->where('work_shift_id', $shiftId)
                    ->update([
                        'override_start_time' => $shiftReq->new_start_time,
                        'override_end_time' => $shiftReq->new_end_time,
                        'updated_at' => now(),
                    ]);
            } elseif ($shiftReq->request_type === 'remove') {
                DB::table('employee_shifts')
                    ->where('employee_id', $employeeId)
                    ->where('work_shift_id', $shiftId)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'อนุมัติคำขอเรียบร้อย',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'ไม่พบคำขอ'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $shiftReq = ShiftRequest::findOrFail($id);

            if ($shiftReq->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'คำขอนี้ได้รับการดำเนินการแล้ว',
                ], 400);
            }

            $user = $request->user();
            $userRole = $user->role ?? 'employee';
            if (!in_array($userRole, ['admin', 'super_admin'])) {
                if (!$user->isSubordinateOf($shiftReq->emp_id)) {
                    return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
                }
            }

            $validated = $request->validate([
                'supervisor_note' => 'required|string|max:500',
            ]);

            $shiftReq->update([
                'status' => 'rejected',
                'supervisor_id' => $user->id,
                'supervisor_note' => $validated['supervisor_note'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ปฏิเสธคำขอเรียบร้อย',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'ไม่พบคำขอ'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
