<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRight extends Model
{
    protected $table = 'approval_rights';

    protected $fillable = [
        'company_id',
        'approver_id',
        'employee_id',
        'can_leave',
        'can_ot',
        'can_wfh',
        'can_shift_swap',
        'can_shift_request',
        'granted_by',
    ];

    protected $casts = [
        'can_leave' => 'boolean',
        'can_ot' => 'boolean',
        'can_wfh' => 'boolean',
        'can_shift_swap' => 'boolean',
        'can_shift_request' => 'boolean',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Column name for a request type key (leave|ot|wfh|shift_swap|shift_request). */
    public static function columnFor(string $type): string
    {
        return 'can_' . $type;
    }

    public static function isDelegatableType(string $type): bool
    {
        return in_array($type, ['leave', 'ot', 'wfh', 'shift_swap', 'shift_request'], true);
    }

    /**
     * Whether $approverId has a delegated right of $type over $requesterId
     * in the same company as the requester.
     */
    public static function canApprove(int $approverId, int $requesterId, string $type): bool
    {
        if (!self::isDelegatableType($type)) {
            return false;
        }

        $column = self::columnFor($type);

        return self::where('approver_id', $approverId)
            ->where('employee_id', $requesterId)
            ->where($column, true)
            ->exists();
    }

    /**
     * All requester IDs this approver may act on via delegated rights (any type,
     * or one type when $type is given).
     *
     * @return array<int, int>
     */
    public static function targetIdsFor(int $approverId, ?string $type = null): array
    {
        $query = self::where('approver_id', $approverId);

        if ($type !== null) {
            if (!self::isDelegatableType($type)) {
                return [];
            }
            $query->where(self::columnFor($type), true);
        }

        return $query->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Extra approver IDs to notify when $requesterId files a request of $type
     * (delegated approvers outside the reports_to chain).
     *
     * @return array<int, int>
     */
    public static function approverIdsFor(int $requesterId, string $type): array
    {
        if (!self::isDelegatableType($type)) {
            return [];
        }

        return self::where('employee_id', $requesterId)
            ->where(self::columnFor($type), true)
            ->pluck('approver_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
