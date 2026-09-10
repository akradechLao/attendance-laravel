<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use App\Models\WfhRecord;
use App\Models\RemoteAssignment;
use App\Models\ShiftSwap;
use App\Models\ShiftRequest;
use App\Models\AttendanceLog;
use App\Models\LateForcedLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingApprovalsController extends Controller
{
    /** Section keys, in the order the UI renders them. */
    private const SECTIONS = [
        'leave', 'ot', 'wfh', 'remote',
        'shift_swap', 'shift_request', 'forced_leave', 'estimated_checkout',
    ];

    private const DEFAULT_PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'type' => 'nullable|in:' . implode(',', self::SECTIONS),
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'counts_only' => 'nullable|boolean',
        ]);

        $only = $validated['type'] ?? null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $countsOnly = $request->boolean('counts_only');

        $subordinateIds = $this->getSubordinateIds($user);
        $isAdmin = in_array($user->role ?? '', ['admin', 'super_admin']);

        // Every section is always counted (the badge must stay accurate), but rows
        // are only loaded for the section being paged into. counts_only skips rows
        // entirely - that is what the sidebar badge asks for.
        $limitFor = function (string $key) use ($only, $perPage, $countsOnly): int {
            if ($countsOnly) {
                return 0;
            }
            return ($only === null || $only === $key) ? $perPage : 0;
        };
        $offsetFor = fn(string $key): int => $only === $key ? ($page - 1) * $perPage : 0;

        $pages = [
            'leave' => $this->getPendingLeaves($user, $subordinateIds, $isAdmin, $limitFor('leave'), $offsetFor('leave')),
            'ot' => $this->getPendingOts($user, $subordinateIds, $isAdmin, $limitFor('ot'), $offsetFor('ot')),
            'wfh' => $this->getPendingWfh($user, $subordinateIds, $isAdmin, $limitFor('wfh'), $offsetFor('wfh')),
            'remote' => $this->getPendingRemote($user, $subordinateIds, $isAdmin, $limitFor('remote'), $offsetFor('remote')),
            'shift_swap' => $this->getPendingShiftSwaps($user, $subordinateIds, $isAdmin, $limitFor('shift_swap'), $offsetFor('shift_swap')),
            'shift_request' => $this->getPendingShiftRequests($user, $subordinateIds, $isAdmin, $limitFor('shift_request'), $offsetFor('shift_request')),
            'forced_leave' => $this->getPendingForcedLeaves($user, $isAdmin, $limitFor('forced_leave'), $offsetFor('forced_leave')),
            'estimated_checkout' => $this->getPendingEstimatedCheckouts($user, $isAdmin, $limitFor('estimated_checkout'), $offsetFor('estimated_checkout')),
        ];

        $data = [];
        $counts = [];
        $hasMore = [];

        foreach (self::SECTIONS as $key) {
            $items = $pages[$key]['items']->values();
            $data[$key] = $items;
            $counts[$key] = $pages[$key]['total'];
            $hasMore[$key] = ($offsetFor($key) + $items->count()) < $pages[$key]['total'];
        }

        $counts['total'] = array_sum($counts);

        $data['counts'] = $counts;
        $data['pagination'] = [
            'type' => $only,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Count the whole result set, then return just the requested slice.
     * A $limit of 0 means "count only" - no rows are fetched.
     *
     * @return array{total:int, items:\Illuminate\Support\Collection}
     */
    private function page($query, int $limit, int $offset, callable $mapper): array
    {
        $total = (clone $query)->count();

        if ($limit === 0 || $offset >= $total) {
            return ['total' => $total, 'items' => collect()];
        }

        return [
            'total' => $total,
            'items' => $query->skip($offset)->take($limit)->get()->map($mapper)->values(),
        ];
    }

    /** @return array{total:int, items:\Illuminate\Support\Collection} */
    private function emptyPage(): array
    {
        return ['total' => 0, 'items' => collect()];
    }

    private function getSubordinateIds($user): array
    {
        if (method_exists($user, 'getAllSubordinateIds')) {
            return $user->getAllSubordinateIds();
        }
        if (method_exists($user, 'getSubordinateIds')) {
            return $user->getSubordinateIds();
        }
        // For AdminUser, return empty (admin sees all via role check)
        return [];
    }

    private function isAdminOrSuperAdmin($user): bool
    {
        return in_array($user->role ?? '', ['admin', 'super_admin']);
    }

    private function getPendingLeaves($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        $query = LeaveRequest::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
            'leaveType:id,name,code',
        ])->where('status', 'pending');

        if ($isAdmin) {
            // Admin sees all pending in their company (or all if super_admin)
            if (!$this->isAdminOrSuperAdmin($user) || empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->whereHas('employee', fn($q) => $q->where('company_id', $user->company_id));
            }
        } elseif (!empty($subordinateIds)) {
            $query->whereIn('emp_id', $subordinateIds);
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($l) => [
            'id' => $l->id,
            'type' => 'leave',
            'employee_name' => $l->employee?->name ?? '-',
            'employee_code' => $l->employee?->employee_code ?? '-',
            'company' => $l->employee?->company?->name ?? '-',
            'department' => $l->employee?->department ?? '-',
            'detail' => ($l->leaveType?->name ?? '-') . ' | ' . $l->start_date . ' ถึง ' . $l->end_date . ' (' . $l->total_days . ' วัน)',
            'reason' => $l->reason,
            'status' => $l->status,
            'created_at' => $l->created_at ? $l->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/leave/{$l->id}/approve",
            'reject_url' => "/api/leave/{$l->id}/reject",
            // LeaveRequestController::reject() (the controller these URLs actually hit)
            // only reads/stores supervisor_note - leave_requests has no rejection_reason column.
            'reject_field' => 'supervisor_note',
        ]);
    }

    private function getPendingOts($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        $query = OtRequest::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->whereIn('status', ['pending_manager', 'pending_hr']);

        if ($isAdmin) {
            if (empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->whereHas('employee', fn($q) => $q->where('company_id', $user->company_id));
            }
        } elseif (!empty($subordinateIds)) {
            $query->whereIn('emp_id', $subordinateIds);
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($o) => [
            'id' => $o->id,
            'type' => 'ot',
            'employee_name' => $o->employee?->name ?? '-',
            'employee_code' => $o->employee?->employee_code ?? '-',
            'company' => $o->employee?->company?->name ?? '-',
            'department' => $o->employee?->department ?? '-',
            'detail' => $o->date . ' | ' . $o->start_time . ' - ' . $o->end_time . ' (' . ($o->total_hours ?? '-') . ' ชม.)',
            'reason' => $o->reason,
            'status' => $o->status,
            'status_label' => $o->status === 'pending_manager' ? 'รอหัวหน้าอนุมัติ' : 'รออนุมัติขั้นสุดท้าย',
            'created_at' => $o->created_at ? $o->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => $o->status === 'pending_manager' ? "/api/ot/{$o->id}/manager-approve" : "/api/ot/{$o->id}/final-approve",
            'reject_url' => "/api/ot/{$o->id}/reject",
            'reject_field' => 'rejection_reason',
        ]);
    }

    private function getPendingWfh($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        $query = WfhRecord::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->where('status', 'pending');

        if ($isAdmin) {
            if (empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->whereHas('employee', fn($q) => $q->where('company_id', $user->company_id));
            }
        } elseif (!empty($subordinateIds)) {
            $query->whereIn('emp_id', $subordinateIds);
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($w) => [
            'id' => $w->id,
            'type' => 'wfh',
            'employee_name' => $w->employee?->name ?? '-',
            'employee_code' => $w->employee?->employee_code ?? '-',
            'company' => $w->employee?->company?->name ?? '-',
            'department' => $w->employee?->department ?? '-',
            'detail' => 'WFH วันที่ ' . $w->date,
            'reason' => $w->reason,
            'status' => $w->status,
            'created_at' => $w->created_at ? $w->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/wfh/{$w->id}/approve",
            'reject_url' => "/api/wfh/{$w->id}/reject",
            'reject_field' => 'supervisor_note',
        ]);
    }

    private function getPendingRemote($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        $query = RemoteAssignment::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->where('status', 'pending');

        if ($isAdmin) {
            if (empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->where('company_id', $user->company_id);
            }
        } elseif (!empty($subordinateIds)) {
            $query->whereIn('emp_id', $subordinateIds);
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($r) => [
            'id' => $r->id,
            'type' => 'remote',
            'employee_name' => $r->employee?->name ?? '-',
            'employee_code' => $r->employee?->employee_code ?? '-',
            'company' => $r->employee?->company?->name ?? '-',
            'department' => $r->employee?->department ?? '-',
            'detail' => $r->start_date . ' ถึง ' . $r->end_date . ' | ' . ($r->destination ?? '-'),
            'reason' => $r->reason,
            'status' => $r->status,
            'created_at' => $r->created_at ? $r->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/remote-assignments/{$r->id}/approve",
            'reject_url' => "/api/remote-assignments/{$r->id}/reject",
            'reject_field' => null,
        ]);
    }

    private function getPendingShiftSwaps($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        // shift_swaps has no emp_id/company_id: it links two employees via requester_id/target_id
        $query = ShiftSwap::with([
            'requester:id,employee_code,name,company_id,position,department,division',
            'requester.company:id,name',
            'target:id,employee_code,name',
        ])->where('status', 'pending');

        if ($isAdmin) {
            if (empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->whereHas('requester', fn($q) => $q->where('company_id', $user->company_id));
            }
        } elseif (!empty($subordinateIds)) {
            // Mirrors ShiftSwapController::reject - supervisor of either side may act
            $query->where(fn($q) => $q->whereIn('requester_id', $subordinateIds)
                                      ->orWhereIn('target_id', $subordinateIds));
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($s) => [
            'id' => $s->id,
            'type' => 'shift_swap',
            'employee_name' => $s->requester?->name ?? '-',
            'employee_code' => $s->requester?->employee_code ?? '-',
            'company' => $s->requester?->company?->name ?? '-',
            'department' => $s->requester?->department ?? '-',
            'detail' => 'สลับเวรวันที่ ' . ($s->swap_date?->format('Y-m-d') ?? '-')
                . ' | ' . ($s->requester_shift ?? '-') . ' ⇄ ' . ($s->target_shift ?? '-')
                . ' (กับ ' . ($s->target?->name ?? '-') . ')',
            'reason' => $s->reason,
            'status' => $s->status,
            'created_at' => $s->created_at ? $s->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/shift-swaps/{$s->id}/approve",
            'reject_url' => "/api/shift-swaps/{$s->id}/reject",
            'reject_field' => 'supervisor_note',
        ]);
    }

    private function getPendingShiftRequests($user, array $subordinateIds, bool $isAdmin, int $limit, int $offset): array
    {
        $query = ShiftRequest::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->where('status', 'pending');

        if ($isAdmin) {
            if (empty($user->company_id)) {
                // super_admin sees all
            } else {
                $query->whereHas('employee', fn($q) => $q->where('company_id', $user->company_id));
            }
        } elseif (!empty($subordinateIds)) {
            $query->whereIn('emp_id', $subordinateIds);
        } else {
            return $this->emptyPage();
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($s) => [
            'id' => $s->id,
            'type' => 'shift_request',
            'employee_name' => $s->employee?->name ?? '-',
            'employee_code' => $s->employee?->employee_code ?? '-',
            'company' => $s->employee?->company?->name ?? '-',
            'department' => $s->employee?->department ?? '-',
            'detail' => ($s->request_type ?? '-') . ' | ' . ($s->start_date?->format('Y-m-d') ?? '-')
                . ($s->end_date && $s->start_date && !$s->end_date->isSameDay($s->start_date) ? ' ถึง ' . $s->end_date->format('Y-m-d') : ''),
            'reason' => $s->reason,
            'status' => $s->status,
            'created_at' => $s->created_at ? $s->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/shift-requests/{$s->id}/approve",
            'reject_url' => "/api/shift-requests/{$s->id}/reject",
            'reject_field' => 'supervisor_note',
        ]);
    }

    private function getPendingForcedLeaves($user, bool $isAdmin, int $limit, int $offset): array
    {
        if (!$isAdmin) {
            return $this->emptyPage();
        }

        $query = LateForcedLeave::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->where('status', 'pending');

        if (empty($user->company_id)) {
            // super_admin sees all
        } else {
            $query->whereHas('employee', fn($q) => $q->where('company_id', $user->company_id));
        }

        return $this->page($query->orderBy('created_at', 'desc'), $limit, $offset, fn($f) => [
            'id' => $f->id,
            'type' => 'forced_leave',
            'employee_name' => $f->employee?->name ?? '-',
            'employee_code' => $f->employee?->employee_code ?? '-',
            'company' => $f->employee?->company?->name ?? '-',
            'department' => $f->employee?->department ?? '-',
            'detail' => 'บังคับลาวันที่ ' . ($f->date ?? '-'),
            'reason' => $f->reason,
            'status' => $f->status,
            'created_at' => $f->created_at ? $f->created_at->setTimezone('Asia/Bangkok')->format('Y-m-d H:i') : null,
            'approve_url' => "/api/attendance-adjustment/forced-leaves/{$f->id}/approve",
            'reject_url' => "/api/attendance-adjustment/forced-leaves/{$f->id}/reject",
            'reject_field' => 'rejection_reason',
        ]);
    }

    private function getPendingEstimatedCheckouts($user, bool $isAdmin, int $limit, int $offset): array
    {
        if (!$isAdmin) {
            return $this->emptyPage();
        }

        $query = AttendanceLog::with([
            'employee:id,employee_code,name,company_id,position,department,division',
            'employee.company:id,name',
        ])->where('is_estimated', true)
          ->whereNull('estimated_approved_by');

        if (empty($user->company_id)) {
            // super_admin sees all
        } else {
            $query->where('company_id', $user->company_id);
        }

        return $this->page($query->orderBy('date', 'desc'), $limit, $offset, fn($a) => [
            'id' => $a->id,
            'type' => 'estimated_checkout',
            'employee_name' => $a->employee?->name ?? '-',
            'employee_code' => $a->employee?->employee_code ?? '-',
            'company' => $a->employee?->company?->name ?? '-',
            'department' => $a->employee?->department ?? '-',
            'detail' => $a->date . ' | ' . ($a->check_in ?? '-') . ' - ' . ($a->check_out ?? '-'),
            'reason' => null,
            'status' => 'pending',
            'created_at' => null,
            'approve_url' => "/api/attendance/estimated-checkouts/{$a->id}/approve",
            'reject_url' => null,
            'reject_field' => null,
        ]);
    }
}
