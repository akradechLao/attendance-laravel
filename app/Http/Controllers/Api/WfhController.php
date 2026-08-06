<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WfhRecord;
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

        $validated['company_id'] = $request->user()->company_id ?? 1;
        $validated['status'] = 'pending';

        $record = WfhRecord::create($validated);

        return response()->json(['data' => $record]);
    }

    public function approve($id)
    {
        $record = WfhRecord::findOrFail($id);
        $record->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_date' => now(),
        ]);

        return response()->json(['message' => 'อนุมัติสำเร็จ']);
    }

    public function reject($id, Request $request)
    {
        $record = WfhRecord::findOrFail($id);
        $record->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json(['message' => 'ไม่อนุมัติสำเร็จ']);
    }
}
