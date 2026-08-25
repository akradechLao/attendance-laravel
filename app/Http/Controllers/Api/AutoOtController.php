<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoOtRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoOtController extends Controller
{
    /**
     * รายการ OT อัตโนมัติ
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $status = $request->get('status');
            $companyId = $request->get('company_id');
            $otType = $request->get('ot_type');

            $query = AutoOtRecord::where('date', $date)
                ->with(['employee', 'employee.company', 'approvedBy'])
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            if ($status) {
                $query->where('status', $status);
            }
            if ($otType) {
                $query->where('ot_type', $otType);
            }

            $records = $query->orderBy('created_at', 'desc')->get()->map(function ($ot) {
                return [
                    'id' => $ot->id,
                    'employee_name' => $ot->employee->name ?? '-',
                    'employee_code' => $ot->employee->employee_code ?? '-',
                    'company_name' => $ot->employee->company->name ?? '-',
                    'date' => $ot->date,
                    'ot_type' => $ot->ot_type,
                    'ot_type_label' => $ot->ot_type === 'before_shift' ? 'มาเร็ว' : 'กลับช้า',
                    'actual_time' => substr($ot->actual_time, 0, 5),
                    'shift_time' => substr($ot->shift_time, 0, 5),
                    'ot_minutes' => $ot->ot_minutes,
                    'ot_hours_display' => intdiv($ot->ot_minutes, 60) . ' ชม. ' . ($ot->ot_minutes % 60) . ' น.',
                    'status' => $ot->status,
                    'status_label' => $ot->status === 'pending' ? 'รออนุมัติ' : ($ot->status === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ'),
                    'reason' => $ot->reason,
                    'approved_by' => $ot->approvedBy->username ?? null,
                    'approved_at' => $ot->approved_at ? $ot->approved_at->format('Y-m-d H:i') : null,
                    'rejection_reason' => $ot->rejection_reason,
                ];
            });

            // สถิติ
            $statsQuery = AutoOtRecord::where('date', $date)
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });
            $totalOt = (clone $statsQuery)->sum('ot_minutes');
            $pendingCount = (clone (clone $statsQuery)->where('status', 'pending'))->count();
            $approvedCount = (clone (clone $statsQuery)->where('status', 'approved'))->count();
            $approvedMinutes = (clone (clone $statsQuery)->where('status', 'approved'))->sum('ot_minutes');

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records,
                    'stats' => [
                        'total_minutes' => $totalOt,
                        'pending_count' => $pendingCount,
                        'approved_count' => $approvedCount,
                        'approved_minutes' => $approvedMinutes,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * อนุมัติ OT
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $ot = AutoOtRecord::findOrFail($id);
            if ($ot->status !== 'pending') {
                return response()->json(['success' => false, 'data' => null, 'message' => 'ไม่อยู่ในสถานะรออนุมัติ'], 400);
            }

            $admin = $request->user();
            $ot->update([
                'status' => 'approved',
                'approved_by' => $admin->id ?? null,
                'approved_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $ot,
                'message' => 'อนุมัติ OT สำเร็จ (' . $ot->ot_minutes . ' นาที)',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ไม่อนุมัติ OT
     */
    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate(['rejection_reason' => 'required|string|max:500']);
            $ot = AutoOtRecord::findOrFail($id);
            if ($ot->status !== 'pending') {
                return response()->json(['success' => false, 'data' => null, 'message' => 'ไม่อยู่ในสถานะรออนุมัติ'], 400);
            }

            $admin = $request->user();
            $ot->update([
                'status' => 'rejected',
                'approved_by' => $admin->id ?? null,
                'approved_at' => Carbon::now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $ot,
                'message' => 'ไม่อนุมัติ OT สำเร็จ',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Record not found.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * อนุมัติทั้งหมด (Verify All)
     */
    public function approveAll(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $companyId = $request->get('company_id');
            $admin = $request->user();

            $query = AutoOtRecord::where('date', $date)
                ->where('status', 'pending')
                ->whereHas('employee', function ($q) use ($companyId) {
                    $q->where('is_active', true);
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                    }
                });

            $count = $query->count();
            $query->update([
                'status' => 'approved',
                'approved_by' => $admin->id ?? null,
                'approved_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => ['count' => $count],
                'message' => 'อนุมัติทั้งหมดสำเร็จ ' . $count . ' รายการ',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }
}
