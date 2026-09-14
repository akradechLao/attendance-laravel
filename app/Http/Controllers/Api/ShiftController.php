<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use App\Models\WorkShift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = ShiftSchedule::with('employee:id,employee_code,name,nickname,photo,company_id,position,department,division');

        if ($request->month) {
            $query->whereMonth('work_date', substr($request->month, 5, 2));
            $query->whereYear('work_date', substr($request->month, 0, 4));
        }

        if ($request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        $shifts = $query->orderBy('work_date')->get();

        $workShifts = WorkShift::orderBy('group_number')->get([
            'id', 'group_number', 'start_time', 'end_time', 'work_hours', 'is_overnight', 'break_start_time', 'break_end_time'
        ])->map(function ($ws) {
            return [
                'id' => $ws->id,
                'group_number' => $ws->group_number,
                'start_time' => $ws->start_time instanceof \Carbon\Carbon ? $ws->start_time->format('H:i') : substr($ws->start_time, 0, 5),
                'end_time' => $ws->end_time instanceof \Carbon\Carbon ? $ws->end_time->format('H:i') : substr($ws->end_time, 0, 5),
                'work_hours' => $ws->work_hours,
                'is_overnight' => $ws->is_overnight,
                'break_start_time' => $ws->break_start_time?->format('H:i'),
                'break_end_time' => $ws->break_end_time?->format('H:i'),
            ];
        });

        return response()->json(['data' => $shifts, 'work_shifts' => $workShifts]);
    }

    /**
     * แก้ไขเวลาพักของกะ (WC0001-WC0016) - ไม่ให้แก้เวลาเข้า-ออก/ชั่วโมงทำงานผ่านจุดนี้
     * เพราะรหัสกะเหล่านั้นผูกกับค่าคงที่ใน ShiftCodeHelper ด้วย แก้เฉพาะที่นี่จะไม่ตรงกัน
     */
    public function updateBreak(Request $request, int $id)
    {
        $validated = $request->validate([
            'break_start_time' => 'nullable|date_format:H:i',
            'break_end_time' => 'nullable|date_format:H:i',
        ]);

        if (($validated['break_start_time'] ?? null) xor ($validated['break_end_time'] ?? null)) {
            return response()->json(['message' => 'กรุณากรอกเวลาพักให้ครบทั้งเริ่มและสิ้นสุด หรือเว้นว่างทั้งคู่'], 422);
        }

        $workShift = WorkShift::findOrFail($id);
        $workShift->update([
            'break_start_time' => $validated['break_start_time'] ?? null,
            'break_end_time' => $validated['break_end_time'] ?? null,
        ]);

        return response()->json(['message' => 'บันทึกเวลาพักสำเร็จ', 'data' => $workShift]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_id' => 'required|exists:employees,id',
            'work_date' => 'required|date',
            'shift_code' => 'nullable|string',
        ]);

        $validated['company_id'] = $this->resolveCompanyId($request);

        $shift = ShiftSchedule::create($validated);

        return response()->json(['data' => $shift]);
    }

    public function destroy($id)
    {
        ShiftSchedule::destroy($id);

        return response()->json(['message' => 'ลบสำเร็จ']);
    }
}
