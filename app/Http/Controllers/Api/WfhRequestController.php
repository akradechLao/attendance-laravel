<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WfhRecord;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WfhRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WfhRecord::with(['employee', 'supervisor']);

        if ($request->has('month')) {
            $month = Carbon::parse($request->month);
            $query->whereYear('date', $month->year)
                  ->whereMonth('date', $month->month);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('emp_id') && $request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $records = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function availableSaturdays(Request $request): JsonResponse
    {
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $availableDays = [];
        $current = $start->copy();

        while ($current <= $end) {
            // Only weekdays (Mon-Fri)
            if ($current->dayOfWeek !== Carbon::SATURDAY && $current->dayOfWeek !== Carbon::SUNDAY) {
                $occupied = WfhRecord::where('date', $current->format('Y-m-d'))
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                $availableDays[] = [
                    'date' => $current->format('Y-m-d'),
                    'day' => $current->format('d'),
                    'day_name' => $current->locale('th')->isoFormat('ddd'),
                    'occupied' => $occupied > 0,
                ];
            }
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $availableDays,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $employee = $request->user();
        $date = Carbon::parse($request->date);

        // Allow weekdays only (Mon-Fri)
        if ($date->dayOfWeek === Carbon::SATURDAY || $date->dayOfWeek === Carbon::SUNDAY) {
            return response()->json([
                'success' => false,
                'message' => 'WFH กำหนดได้เฉพาะวันจันทร์-ศุกร์เท่านั้น',
            ], 400);
        }

        $month = $date->format('Y-m');
        $existing = WfhRecord::where('emp_id', $employee->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->where('status', '!=', 'rejected')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'คุณมีรายการ WFH ประจำเดือนนี้แล้ว',
            ], 400);
        }

        $occupied = WfhRecord::where('date', $date->format('Y-m-d'))
            ->whereIn('status', ['pending', 'approved'])
            ->where('emp_id', '!=', $employee->id)
            ->count();

        if ($occupied > 0) {
            return response()->json([
                'success' => false,
                'message' => 'วันนี้มีพนักงานอื่นใช้แล้ว กรุณาเลือกวันอื่น',
            ], 400);
        }

        $record = WfhRecord::create([
            'emp_id' => $employee->id,
            'date' => $date->format('Y-m-d'),
            'reason' => $request->get('reason', ''),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $record->load('employee'),
            'message' => 'ส่งคำขอ WFH สำเร็จ รอหัวหน้าอนุมัติ',
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        if ($record->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'รายการนี้ดำเนินการแล้ว',
            ], 400);
        }

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($record->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $approvedDate = $request->get('approved_date', $record->date);

        if (Carbon::parse($approvedDate)->dayOfWeek !== Carbon::SATURDAY) {
            return response()->json([
                'success' => false,
                'message' => 'วันที่อนุมัติต้องเป็นวันเสาร์',
            ], 400);
        }

        $dateConflict = WfhRecord::where('date', $approvedDate)
            ->whereIn('status', ['pending', 'approved'])
            ->where('emp_id', $record->emp_id)
            ->where('id', '!=', $id)
            ->exists();

        if ($dateConflict) {
            return response()->json([
                'success' => false,
                'message' => 'วันเสาร์นี้มีคนใช้แล้ว',
            ], 400);
        }

        $record->update([
            'date' => $approvedDate,
            'approved_date' => $approvedDate,
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note'),
            'status' => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'data' => $record->load(['employee', 'supervisor']),
            'message' => 'อนุมัติ WFH สำเร็จ',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);

        // Authorization: must be subordinate or HR admin
        $user = $request->user();
        $userRole = $user->role ?? 'employee';
        if (!in_array($userRole, [RoleConstants::ADMIN, RoleConstants::SUPER_ADMIN])) {
            if (!$user->isSubordinateOf($record->emp_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: not your subordinate'], 403);
            }
        }

        $record->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'rejected',
        ]);

        return response()->json([
            'success' => true,
            'data' => $record,
            'message' => 'ปฏิเสธคำขอ WFH',
        ]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $record = WfhRecord::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'ยกเลิก WFH สำเร็จ',
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employee = $request->user();
        $month = $request->get('month', Carbon::now()->format('Y-m'));

        $quota = $employee->wfh_quota ?? 1;

        $records = WfhRecord::where('emp_id', $employee->id)
            ->whereYear('date', Carbon::parse($month)->year)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->with('supervisor')
            ->orderBy('date', 'desc')
            ->get();

        $used = $records->where('status', 'approved')->count();

        return response()->json([
            'success' => true,
            'data' => $records,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'quota' => $quota,
        ]);
    }

    public function teamRequests(Request $request): JsonResponse
    {
        $employee = $request->user();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found'], 404);
        }

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $employeeIds = $employee->getAllSubordinateIds();

        if (empty($employeeIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $records = WfhRecord::whereIn('emp_id', $employeeIds)
            ->whereYear('date', Carbon::parse($month)->year)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->with('employee')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}
