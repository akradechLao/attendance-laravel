<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ShiftCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Models\OfficeLocation;
use App\Models\OfficeLocationShiftPattern;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeLocationShiftPatternController extends Controller
{
    public function index(int $locationId): JsonResponse
    {
        $location = OfficeLocation::findOrFail($locationId);
        $patterns = $location->shiftPatterns()->with('workShift')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $patterns,
        ]);
    }

    public function store(Request $request, int $locationId): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($locationId);

            $validated = $request->validate([
                'work_shift_id' => 'required|exists:work_shifts,id',
                'days_of_week' => 'required|array|min:1',
                'days_of_week.*' => 'integer|between:0,6',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
                'is_active' => 'boolean',
            ]);

            $pattern = $location->shiftPatterns()->create($validated);
            $pattern->load('workShift');

            return response()->json([
                'success' => true,
                'data' => $pattern,
                'message' => 'บันทึกรูปแบบกะเรียบร้อย',
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

    public function update(Request $request, int $locationId, int $patternId): JsonResponse
    {
        try {
            $pattern = OfficeLocationShiftPattern::where('office_location_id', $locationId)->findOrFail($patternId);

            $validated = $request->validate([
                'work_shift_id' => 'sometimes|exists:work_shifts,id',
                'days_of_week' => 'sometimes|array|min:1',
                'days_of_week.*' => 'integer|between:0,6',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
                'is_active' => 'boolean',
            ]);

            $pattern->update($validated);
            $pattern->load('workShift');

            return response()->json([
                'success' => true,
                'data' => $pattern,
                'message' => 'บันทึกรูปแบบกะเรียบร้อย',
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

    public function destroy(int $locationId, int $patternId): JsonResponse
    {
        $pattern = OfficeLocationShiftPattern::where('office_location_id', $locationId)->findOrFail($patternId);
        $pattern->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบรูปแบบกะเรียบร้อย',
        ]);
    }

    /**
     * สร้างตารางกะ (shift_schedules) ให้พนักงานที่ถูกมอบหมายพื้นที่นี้ ตามรูปแบบกะที่ตั้งไว้
     * สำหรับเดือนที่เลือก - จะข้ามวันที่พนักงานมีการกำหนดกะไว้แล้ว (ทั้งจาก shift_schedules
     * และ roster ใน employee_shifts) เพื่อไม่ให้ทับการปรับเปลี่ยน/คำขอที่อนุมัติไปแล้ว
     */
    public function generate(Request $request, int $locationId): JsonResponse
    {
        try {
            $location = OfficeLocation::findOrFail($locationId);

            $validated = $request->validate([
                'month' => 'required|date_format:Y-m',
                'skip_holiday' => 'boolean',
            ]);

            $skipHoliday = $validated['skip_holiday'] ?? true;
            $startDate = $validated['month'] . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $patterns = $location->shiftPatterns()->where('is_active', true)->with('workShift')->get();
            if ($patterns->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'พื้นที่นี้ยังไม่มีรูปแบบกะ กรุณาตั้งค่าก่อน',
                ], 422);
            }

            $employees = $location->assignedEmployees()->where('is_active', true)->get();
            if ($employees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'พื้นที่นี้ยังไม่มีพนักงานที่ถูกมอบหมาย',
                ], 422);
            }
            $empIds = $employees->pluck('id')->toArray();

            $holidays = [];
            if ($skipHoliday) {
                $holidays = CompanyHoliday::where('company_id', $location->company_id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->pluck('date')
                    ->map(fn($d) => $d->format('Y-m-d'))
                    ->toArray();
            }

            // วันที่พนักงานมีกะกำหนดไว้แล้ว (ทั้งแบบรายวันและ roster) - ข้ามไปไม่ยุ่ง
            $existingScheduleDates = DB::table('shift_schedules')
                ->whereIn('emp_id', $empIds)
                ->whereBetween('work_date', [$startDate, $endDate])
                ->get(['emp_id', 'work_date'])
                ->map(fn($r) => $r->emp_id . '_' . $r->work_date)
                ->flip();

            $rosterRanges = DB::table('employee_shifts')
                ->whereIn('employee_id', $empIds)
                ->where(function ($q) use ($endDate) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', $endDate);
                })
                ->where(function ($q) use ($startDate) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate);
                })
                ->pluck('employee_id')
                ->unique()
                ->flip();

            $created = 0;
            $skippedExisting = 0;
            $skippedHoliday = 0;
            $skippedNoPattern = 0;

            DB::beginTransaction();
            try {
                foreach ($empIds as $empId) {
                    // มี roster ประจำเดือนนี้อยู่แล้ว (มอบหมายผ่านหน้าจัดการกะ/คำขอที่อนุมัติ) - ข้ามทั้งเดือน
                    if ($rosterRanges->has($empId)) {
                        continue;
                    }

                    $current = strtotime($startDate);
                    $end = strtotime($endDate);
                    while ($current <= $end) {
                        $dateStr = date('Y-m-d', $current);
                        $current = strtotime('+1 day', $current);

                        if ($existingScheduleDates->has($empId . '_' . $dateStr)) {
                            $skippedExisting++;
                            continue;
                        }

                        if (in_array($dateStr, $holidays, true)) {
                            $skippedHoliday++;
                            continue;
                        }

                        $matched = $patterns->first(fn($p) => $p->matchesDate($dateStr));
                        if (!$matched) {
                            $skippedNoPattern++;
                            continue;
                        }

                        $code = ShiftCodeHelper::codeFromGroup($matched->workShift->group_number)
                            ?? 'WC' . str_pad($matched->workShift->group_number + 1, 4, '0', STR_PAD_LEFT);

                        DB::table('shift_schedules')->insert([
                            'company_id' => $location->company_id,
                            'emp_id' => $empId,
                            'work_date' => $dateStr,
                            'shift_code' => $code,
                            'day_type' => 'working',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $created++;
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => "สร้างตารางกะ {$created} วัน (ข้ามวันที่มีกะอยู่แล้ว {$skippedExisting}, วันหยุด {$skippedHoliday})",
                'data' => compact('created', 'skippedExisting', 'skippedHoliday', 'skippedNoPattern'),
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
