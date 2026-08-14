<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSwap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShiftSwap::with(['requester', 'target', 'supervisor']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('supervisor_id') && $request->supervisor_id) {
            $employeeIds = Employee::where('supervisor_id', $request->supervisor_id)->pluck('id')->toArray();
            $employeeIds[] = $request->supervisor_id;
            $query->whereIn('requester_id', $employeeIds);
        }

        $swaps = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requester_id' => 'required|exists:employees,id',
            'target_id' => 'required|exists:employees,id',
            'swap_date' => 'required|date',
            'requester_shift' => 'required|string',
            'target_shift' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        if ($validated['requester_id'] == $validated['target_id']) {
            return response()->json(['success' => false, 'message' => 'ไม่สามารถสลับกับตัวเองได้'], 400);
        }

        $swap = ShiftSwap::create($validated);

        return response()->json([
            'success' => true,
            'data' => $swap->load(['requester', 'target']),
            'message' => 'ส่งคำขอสลับกะสำเร็จ',
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $swap = ShiftSwap::findOrFail($id);

        if ($swap->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'รายการนี้ดำเนินการแล้ว'], 400);
        }

        $swap->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'data' => $swap,
            'message' => 'อนุมัติสลับกะสำเร็จ',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $swap = ShiftSwap::findOrFail($id);

        $swap->update([
            'supervisor_id' => $request->get('supervisor_id'),
            'supervisor_note' => $request->get('supervisor_note', ''),
            'status' => 'rejected',
        ]);

        return response()->json([
            'success' => true,
            'data' => $swap,
            'message' => 'ปฏิเสธคำขอสลับกะ',
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $empId = $request->get('emp_id');
        $swaps = ShiftSwap::where('requester_id', $empId)
            ->with(['target', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }

    public function teamSwaps(Request $request): JsonResponse
    {
        $supervisorId = $request->get('supervisor_id');
        $employeeIds = Employee::where('supervisor_id', $supervisorId)->pluck('id')->toArray();
        $employeeIds[] = $supervisorId;

        $swaps = ShiftSwap::whereIn('requester_id', $employeeIds)
            ->with(['requester', 'target'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $swaps]);
    }
}
