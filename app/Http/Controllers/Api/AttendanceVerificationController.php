<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Helpers\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceVerificationController extends Controller
{
    /**
     * รายการเข้างานที่รอตรวจสอบ (is_verified = 0)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $companyId = $request->get('company_id');
            $verified = $request->get('verified'); // '0' = รอตรวจสอบ, '1' = ตรวจสอบแล้ว, null = ทั้งหมด

            $query = AttendanceLog::where('date', $date)
                ->with(['employee', 'employee.company', 'verifiedBy'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            if ($verified === '0') {
                $query->where('is_verified', false);
            } elseif ($verified === '1') {
                $query->where('is_verified', true);
            }

            $records = $query->orderBy('check_in', 'desc')->get()->map(function ($log) {
                $checkIn = null;
                $checkOut = null;

                if ($log->check_in instanceof Carbon) {
                    $checkIn = $log->check_in->format('H:i');
                }
                if ($log->check_out instanceof Carbon) {
                    $checkOut = $log->check_out->format('H:i');
                }

                $employee = $log->employee;
                $logDate = $log->date instanceof Carbon ? $log->date->toDateString() : $log->date;
                $shift = $employee->workShifts()->where(function ($q) use ($logDate) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', $logDate);
                })->where(function ($q) use ($logDate) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $logDate);
                })->first();

                $shiftTime = '-';
                $shiftStart = null;
                $shiftEnd = null;
                if ($shift) {
                    $shiftStart = $shift->start_time instanceof Carbon ? $shift->start_time->format('H:i') : $shift->start_time;
                    $shiftEnd = $shift->end_time instanceof Carbon ? $shift->end_time->format('H:i') : $shift->end_time;
                    $shiftTime = $shiftStart . '-' . $shiftEnd;
                }

                return [
                    'id' => $log->id,
                    'employee_id' => $log->emp_id,
                    'employee_name' => $employee->name ?? '-',
                    'employee_code' => $employee->employee_code ?? '-',
                    'company_name' => $employee->company->name ?? '-',
                    'date' => $log->date,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'original_status' => $log->original_status ?? $log->check_in_status,
                    'final_status' => $log->final_status ?? $log->original_status ?? $log->check_in_status,
                    'late_minutes' => $log->late_minutes,
                    'scan_type' => $log->scan_type,
                    'shift_time' => $shiftTime,
                    'shift_start' => $shiftStart,
                    'shift_end' => $shiftEnd,
                    'is_verified' => $log->is_verified,
                    'verified_by' => $log->verifiedBy->username ?? null,
                    'verified_at' => $log->verified_at ? $log->verified_at->format('Y-m-d H:i') : null,
                ];
            });

            // สถิติ
            $totalQuery = AttendanceLog::where('date', $date)
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });
            $totalCount = (clone $totalQuery)->count();
            $verifiedCount = (clone $totalQuery)->where('is_verified', true)->count();
            $unverifiedCount = $totalCount - $verifiedCount;

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records,
                    'stats' => [
                        'total' => $totalCount,
                        'verified' => $verifiedCount,
                        'unverified' => $unverifiedCount,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ยืนยันรายการเดียว
     */
    public function verify(Request $request, $id): JsonResponse
    {
        try {
            $log = AttendanceLog::findOrFail($id);
            $admin = $request->user();

            $log->update([
                'is_verified' => true,
                'verified_by' => $admin->id ?? null,
                'verified_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'is_verified' => true,
                    'verified_by' => $admin->username ?? 'Admin',
                    'verified_at' => $log->verified_at->format('Y-m-d H:i'),
                ],
                'message' => 'ยืนยันรายการสำเร็จ',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ยืนยันทั้งหมด (Verify All)
     */
    public function verifyAll(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $companyId = $request->get('company_id');
            $admin = $request->user();

            $query = AttendanceLog::where('date', $date)
                ->where('is_verified', false)
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            $count = $query->count();

            $query->update([
                'is_verified' => true,
                'verified_by' => $admin->id ?? null,
                'verified_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['count' => $count],
                'message' => 'ยืนยันทั้งหมดสำเร็จ ' . $count . ' รายการ',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ยกเลิกการยืนยัน (Unverify)
     */
    public function unverify(Request $request, $id): JsonResponse
    {
        try {
            $log = AttendanceLog::findOrFail($id);

            $log->update([
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['id' => $log->id, 'is_verified' => false],
                'message' => 'ยกเลิกการยืนยันสำเร็จ',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }
}
