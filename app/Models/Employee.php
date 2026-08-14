<?php
namespace App\Models;

use App\Constants\PositionConstants;
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
        'birth_date',
        'id_card',
        'social_security',
        'education',
        'telegram_chat_id',
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
        'role',
        'wfh_quota',
        'preferred_off_day',
        'is_active',
        'office_location_id',
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
        return $this->belongsToMany(WorkShift::class, 'employee_shifts')
            ->withPivot('start_date', 'end_date');
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
        return $this->company->officeLocations()->where('is_active', true)->first();
    }

    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
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