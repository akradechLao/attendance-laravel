<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WfhRecord;
use App\Models\Employee;
use App\Models\RemoteAssignment;
use App\Constants\RoleConstants;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WfhController extends Controller
{
    public function index(Request $request)
    {
        $query = WfhRecord::with('employee');

        if ($request->emp_id) {
            $query->where('emp_id', $request->emp_id);
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $records]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $validated['company_id'] = $this->resolveCompanyId($request);
        $validated['status'] = 'pending';

        $record = WfhRecord::create($validated);

        return response()->json(['data' => $record]);
    }

    public function approve($id, Request $request)
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
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_date' => now(),
        ]);

        // ─── สร้าง RemoteAssignment อัตโนมัติ ───
        $employee = $record->employee;
        if ($employee) {
            $wfhDate = Carbon::parse($record->date ?? $record->start_date)->format('Y-m-d');
            RemoteAssignment::where('emp_id', $record->emp_id)
                ->where('start_date', $wfhDate)
                ->where('end_date', $wfhDate)
                ->delete();

            RemoteAssignment::create([
                'emp_id' => $record->emp_id,
                'company_id' => $employee->company_id,
                'start_date' => $wfhDate,
                'end_date' => $wfhDate,
                'destination' => 'WFH',
                'reason' => $record->reason ?: 'ปฏิบัติงานนอกสถานที่ (WFH)',
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        return response()->json(['message' => 'อนุมัติสำเร็จ']);
    }

    public function reject($id, Request $request)
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
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        // ─── ลบ RemoteAssignment ของวัน WFH นี้ (ถ้ามี) ───
        $employee = $record->employee;
        if ($employee) {
            $wfhDate = Carbon::parse($record->date ?? $record->start_date)->format('Y-m-d');
            RemoteAssignment::where('emp_id', $record->emp_id)
                ->where('start_date', $wfhDate)
                ->where('end_date', $wfhDate)
                ->where('destination', 'WFH')
                ->delete();
        }

        return response()->json(['message' => 'ไม่อนุมัติสำเร็จ']);
    }
}
