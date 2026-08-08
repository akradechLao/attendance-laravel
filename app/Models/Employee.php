<?php
namespace App\Models;

use App\Constants\PositionConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'birth_date',
        'start_date',
        'employee_code',
        'group_type',
        'position',
        'level',
        'has_ot',
        'department',
        'division',
        'reports_to',
        'supervisor_name',
        'supervisor_line',
        'supervisor_phone',
        'password',
        'wfh_quota',
        'preferred_off_day',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'has_ot' => 'boolean',
        'level' => 'integer',
        'group_type' => 'integer',
        'password' => 'hashed',
        'wfh_quota' => 'integer',
    ];

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
        return $this->belongsToMany(WorkShift::class, 'employee_shifts');
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
        return PositionConstants::isExcluded($this->position);
    }

    public function getLevel(): int
    {
        return PositionConstants::getLevel($this->position);
    }

    public function canApprove(): bool
    {
        $level = $this->getLevel();
        return $level <= PositionConstants::HIERARCHY['division_manager'];
    }

    public function hasActiveRemoteAssignment(): bool
    {
        return $this->remoteAssignments()
            ->where('status', 'approved')
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->exists();
    }
}
