<?php
namespace App\Models;

use App\Constants\PositionConstants;
use App\Constants\RoleConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'name',
        'nickname',
        'phone',
        'email',
        'photo',
        'birth_date',
        'id_card',
        'social_security',
        'education',
        'telegram_chat_id',
        'start_date',
        'employee_code',
        'group_type',
        'position',
        'position_level',
        'level',
        'has_ot',
        'department',
        'division',
        'reports_to',
        'supervisor_name',
        'supervisor_line',
        'supervisor_phone',
        'password',
        'role',
        'wfh_quota',
        'preferred_off_day',
        'is_active',
        'office_location_id',
    ];

    protected $hidden = [
        'password',
        'id_card',
        'social_security',
        'phone',
        'email',
        'birth_date',
        'telegram_chat_id',
        'supervisor_phone',
        'supervisor_line',
        'pin',
    ];

    protected $casts = [
        'has_ot' => 'boolean',
        'level' => 'integer',
        'group_type' => 'integer',
        'password' => 'hashed',
        'wfh_quota' => 'integer',
    ];

    // Role helpers
    public function isEmployee(): bool
    {
        return $this->role === RoleConstants::EMPLOYEE;
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleConstants::ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === RoleConstants::SUPER_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function getRoleLabelAttribute(): string
    {
        return RoleConstants::LABELS[$this->role] ?? $this->role;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function faceData(): HasMany
    {
        return $this->hasMany(EmployeeFaceData::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'emp_id');
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reports_to');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'emp_id');
    }

    public function otRequests(): HasMany
    {
        return $this->hasMany(OtRequest::class, 'emp_id');
    }

    public function remoteAssignments(): HasMany
    {
        return $this->hasMany(RemoteAssignment::class, 'emp_id');
    }

    public function workShifts()
    {
        return $this->belongsToMany(WorkShift::class, 'employee_shifts')
            ->withPivot('start_date', 'end_date', 'override_start_time', 'override_end_time');
    }

    public function approvers()
    {
        return $this->hasMany(EmployeeApprover::class);
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'emp_id');
    }

    public function wfhRecords(): HasMany
    {
        return $this->hasMany(WfhRecord::class, 'emp_id');
    }

    public function isExcludedFromAttendance(): bool
    {
        return PositionConstants::isExcluded($this->position_level);
    }

    public function getLevel(): int
    {
        return PositionConstants::getLevel($this->position_level);
    }

    public function canApprove(): bool
    {
        $level = $this->getLevel();
        return $level <= PositionConstants::HIERARCHY['division_manager'];
    }

    /**
     * Get human-readable Thai name for the coded position_level (not the
     * free-text `position` job title, which is displayed as-is elsewhere).
     */
    public function getPositionName(): string
    {
        return PositionConstants::LEVEL_LABELS_TH[$this->position_level] ?? 'พนักงาน';
    }

    /**
     * Accessor: first_name derived from name column.
     * DB has single "name" column, but code expects first_name/last_name.
     */
    public function getFirstNameAttribute(): ?string
    {
        $name = $this->attributes['name'] ?? '';
        $parts = preg_split('/\s+/', trim($name), 3);
        // Skip title (นาย/นาง/นางสาว/เด็กชาย/เด็กหญิง) if present
        $titles = ['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง', 'ด.ช.', 'ด.ญ.'];
        if (isset($parts[0]) && in_array($parts[0], $titles)) {
            return $parts[1] ?? null;
        }
        return $parts[0] ?? null;
    }

    /**
     * Accessor: last_name derived from name column.
     */
    public function getLastNameAttribute(): ?string
    {
        $name = $this->attributes['name'] ?? '';
        $parts = preg_split('/\s+/', trim($name), 3);
        $titles = ['นาย', 'นาง', 'นางสาว', 'เด็กชาย', 'เด็กหญิง', 'ด.ช.', 'ด.ญ.'];
        if (isset($parts[0]) && in_array($parts[0], $titles)) {
            return $parts[2] ?? null;
        }
        return $parts[1] ?? null;
    }

    /**
     * Accessor: display_name returns "ชื่อ (รหัส)" format.
     * Use this everywhere to prevent confusion when employees have the same name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->employee_code . ')';
    }

    /**
     * Check if $employeeId is a direct or indirect subordinate of this employee.
     * Walks up the reports_to chain from the target employee.
     */
    public function isSubordinateOf(int $employeeId): bool
    {
        if ($this->id === $employeeId) {
            return false;
        }

        $current = Employee::find($employeeId);
        // reports_to has no company constraint at the database level - stop
        // walking the moment the chain leaves this employee's company so a
        // stray cross-company link can't grant approval visibility across
        // tenants.
        if (!$current || $current->company_id !== $this->company_id) {
            return false;
        }
        $maxDepth = 10;

        while ($current && $maxDepth > 0) {
            if ($current->reports_to === $this->id) {
                return true;
            }
            $current = Employee::find($current->reports_to);
            if ($current && $current->company_id !== $this->company_id) {
                return false;
            }
            $maxDepth--;
        }

        return false;
    }

    /**
     * Get all subordinate employee IDs (direct + indirect).
     */
    public function getAllSubordinateIds(): array
    {
        $ids = [];
        $this->collectSubordinates($this->id, $ids);
        return $ids;
    }

    private function collectSubordinates(int $parentId, array &$ids, int $depth = 0): void
    {
        if ($depth > 10) {
            return;
        }

        // Scoped to $this->company_id so a stray cross-company reports_to link
        // can't pull another tenant's employees into this list.
        $children = Employee::where('reports_to', $parentId)
            ->where('company_id', $this->company_id)
            ->pluck('id')->toArray();
        foreach ($children as $childId) {
            $ids[] = $childId;
            $this->collectSubordinates($childId, $ids, $depth + 1);
        }
    }

    /**
     * Get direct supervisor ID (the employee this person reports to).
     */
    public function getDirectSupervisorId(): ?int
    {
        return $this->reports_to;
    }

    /**
     * Get all supervisor IDs up the chain (direct + indirect).
     */
    public function getSupervisorIds(): array
    {
        $ids = [];
        $current = $this;
        $maxDepth = 10;

        while ($current && $current->reports_to && $maxDepth > 0) {
            $next = Employee::find($current->reports_to);
            // Stop at the company boundary - see isSubordinateOf() for why.
            if (!$next || $next->company_id !== $this->company_id) {
                break;
            }
            $ids[] = $current->reports_to;
            $current = $next;
            $maxDepth--;
        }

        return array_unique($ids);
    }

    public function hasActiveRemoteAssignment(): bool
    {
        return $this->remoteAssignments()
            ->where('status', 'approved')
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function assignedOfficeLocations()
    {
        return $this->belongsToMany(OfficeLocation::class, 'employee_office_locations');
    }

    public function getAssignedOfficeLocation()
    {
        $assigned = $this->assignedOfficeLocations()->first();
        if ($assigned) {
            return $assigned;
        }
        if (!$this->company) {
            return null;
        }
        return $this->company->officeLocations()->where('is_active', true)->first();
    }

    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return \App\Services\LocationService::calculateDistance($lat1, $lon1, $lat2, $lon2);
    }

    public function getDistanceToOffice(?float $lat, ?float $lon): ?array
    {
        $office = $this->getAssignedOfficeLocation();
        if (!$office || $lat === null || $lon === null) {
            return null;
        }

        $distance = self::calculateDistance($lat, $lon, $office->latitude, $office->longitude);

        return [
            'distance_meters' => round($distance),
            'within_radius' => $distance <= $office->radius_meters,
            'radius_meters' => $office->radius_meters,
            'office_name' => $office->name,
            'message' => $distance <= $office->radius_meters
                ? 'อยู่ในรัศมี ' . round($distance) . ' เมตร จาก ' . $office->name
                : 'อยู่ห่าง ' . round($distance) . ' เมตร จาก ' . $office->name . ' (เกินรัศมี ' . $office->radius_meters . ' เมตร)',
        ];
    }

}