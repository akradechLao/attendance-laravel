<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRight;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalRightController extends Controller
{
    private const TYPES = ['leave', 'ot', 'wfh', 'shift_swap', 'shift_request'];

    /**
     * Existing delegated rights for one approver (for the UI to pre-check boxes).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'approver_id' => 'required|integer|exists:employees,id',
        ]);

        $approver = Employee::findOrFail($validated['approver_id']);
        $rights = ApprovalRight::where('approver_id', $approver->id)
            ->where('company_id', $approver->company_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rights,
        ]);
    }

    /**
     * Candidate list for the checkbox table: employees in scope, plus
     * is_chain (already can approve via reports_to) and existing grants.
     */
    public function candidates(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'approver_id' => 'nullable|integer|exists:employees,id',
            'division' => 'nullable|string',
            'department' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'company_id' => 'nullable|integer',
        ]);

        $approver = null;
        if (!empty($validated['approver_id'])) {
            $approver = Employee::find($validated['approver_id']);
            if (!$approver) {
                return response()->json(['success' => false, 'message' => 'ไม่พบผู้อนุมัติ'], 404);
            }
        }

        $isSuper = ($user->role ?? '') === 'super_admin';
        $userCompanyId = (int) ($user->company_id ?? 0);

        $query = Employee::where('is_active', true)
            ->select('id', 'employee_code', 'name', 'nickname', 'position', 'position_level', 'division', 'department', 'company_id', 'reports_to');

        if ($approver) {
            $query->where('company_id', $approver->company_id);
            $query->where('id', '!=', $approver->id);
        } elseif ($isSuper) {
            if (!empty($validated['company_id'])) {
                $query->where('company_id', $validated['company_id']);
            }
        } elseif ($userCompanyId) {
            $query->where('company_id', $userCompanyId);
        }

        if (!empty($validated['division'])) {
            $query->where('division', $validated['division']);
        }
        if (!empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $employees = $query
            ->orderBy('division')
            ->orderBy('department')
            ->orderBy('employee_code')
            ->get();

        // Filter options (all active employees in scope, ignoring search)
        $filterBase = Employee::where('is_active', true);
        if ($approver) {
            $filterBase->where('company_id', $approver->company_id);
        } elseif ($isSuper) {
            if (!empty($validated['company_id'])) {
                $filterBase->where('company_id', $validated['company_id']);
            }
        } elseif ($userCompanyId) {
            $filterBase->where('company_id', $userCompanyId);
        }
        $divisions = (clone $filterBase)->whereNotNull('division')->distinct()->pluck('division')->sort()->values();
        $deptQuery = (clone $filterBase);
        if (!empty($validated['division'])) {
            $deptQuery->where('division', $validated['division']);
        }
        $departments = $deptQuery->whereNotNull('department')->distinct()->pluck('department')->sort()->values();

        $grants = [];
        $chainIds = [];
        if ($approver) {
            $grants = ApprovalRight::where('approver_id', $approver->id)
                ->where('company_id', $approver->company_id)
                ->get()
                ->keyBy('employee_id');
            $chainIds = array_flip($approver->getAllSubordinateIds());
        }

        $data = $employees->map(function ($emp) use ($grants, $chainIds, $approver) {
            $grant = $approver ? ($grants->get($emp->id) ?? null) : null;

            return [
                'id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'name' => $emp->name,
                'nickname' => $emp->nickname,
                'position' => $emp->position,
                'position_level' => $emp->position_level,
                'division' => $emp->division,
                'department' => $emp->department,
                'company_id' => $emp->company_id,
                'is_chain' => isset($chainIds[$emp->id]),
                'is_self' => $approver && $emp->id === $approver->id,
                'can_leave' => $grant ? (bool) $grant->can_leave : false,
                'can_ot' => $grant ? (bool) $grant->can_ot : false,
                'can_wfh' => $grant ? (bool) $grant->can_wfh : false,
                'can_shift_swap' => $grant ? (bool) $grant->can_shift_swap : false,
                'can_shift_request' => $grant ? (bool) $grant->can_shift_request : false,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'filters' => [
                'divisions' => $divisions,
                'departments' => $departments,
            ],
        ]);
    }

    /**
     * Bulk save grants for one approver. Payload is the full intended set:
     * rows with all flags false (or chain rows) are removed; others upserted.
     */
    public function save(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'approver_id' => 'required|integer|exists:employees,id',
            'grants' => 'required|array',
            'grants.*.employee_id' => 'required|integer|exists:employees,id',
            'grants.*.can_leave' => 'nullable|boolean',
            'grants.*.can_ot' => 'nullable|boolean',
            'grants.*.can_wfh' => 'nullable|boolean',
            'grants.*.can_shift_swap' => 'nullable|boolean',
            'grants.*.can_shift_request' => 'nullable|boolean',
        ]);

        $approver = Employee::find($validated['approver_id']);
        if (!$approver) {
            return response()->json(['success' => false, 'message' => 'ไม่พบผู้อนุมัติ'], 404);
        }

        // Non-super admins may only grant inside their own company
        if (($user->role ?? '') !== 'super_admin') {
            if ((int) $approver->company_id !== (int) ($user->company_id ?? 0)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: cross-company access denied'], 403);
            }
        }

        $chain = array_flip($approver->getAllSubordinateIds());
        $keepIds = [];

        DB::transaction(function () use ($validated, $approver, $user, $chain, &$keepIds) {
            foreach ($validated['grants'] as $row) {
                $employeeId = (int) $row['employee_id'];
                if ($employeeId === (int) $approver->id || isset($chain[$employeeId])) {
                    continue;
                }

                $flags = [
                    'can_leave' => (bool) ($row['can_leave'] ?? false),
                    'can_ot' => (bool) ($row['can_ot'] ?? false),
                    'can_wfh' => (bool) ($row['can_wfh'] ?? false),
                    'can_shift_swap' => (bool) ($row['can_shift_swap'] ?? false),
                    'can_shift_request' => (bool) ($row['can_shift_request'] ?? false),
                ];

                if (!in_array(true, $flags, true)) {
                    ApprovalRight::where('approver_id', $approver->id)
                        ->where('employee_id', $employeeId)
                        ->delete();
                    continue;
                }

                $target = Employee::find($employeeId);
                if (!$target || $target->company_id !== $approver->company_id) {
                    continue;
                }

                ApprovalRight::updateOrCreate(
                    [
                        'approver_id' => $approver->id,
                        'employee_id' => $employeeId,
                    ],
                    array_merge($flags, [
                        'company_id' => $approver->company_id,
                        'granted_by' => $user->id ?? null,
                    ])
                );
                $keepIds[] = $employeeId;
            }

            // Only remove rows the client explicitly submitted as "all off".
            // Rows outside the current filter (not in payload) must survive.
            $submitted = array_map(fn ($r) => (int) $r['employee_id'], $validated['grants']);
            ApprovalRight::where('approver_id', $approver->id)
                ->whereIn('employee_id', $submitted)
                ->whereNotIn('employee_id', $keepIds)
                ->delete();
        });

        AuditLogService::action(
            'update',
            $approver,
            'อัปเดตสิทธิ์อนุมัติเสริมของ ' . $approver->name . ' (' . $approver->employee_code . ')',
            $request
        );

        $rights = ApprovalRight::where('approver_id', $approver->id)->get();

        return response()->json([
            'success' => true,
            'data' => $rights,
            'message' => 'บันทึกสิทธิ์อนุมัติสำเร็จ',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $right = ApprovalRight::findOrFail($id);
        $right->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบสิทธิ์อนุมัติแล้ว',
        ]);
    }
}
