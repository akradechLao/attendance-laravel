<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code_prefix',
        'logo',
        'phone',
        'email',
        'address',
        'website',
        'telegram_bot_token',
        'telegram_chat_id',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;
        return '/storage/companies/' . $this->logo;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function adminUsers(): HasMany
    {
        return $this->hasMany(AdminUser::class);
    }

    public function officeLocations(): HasMany
    {
        return $this->hasMany(OfficeLocation::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(CompanyHoliday::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function otRequests(): HasMany
    {
        return $this->hasMany(OtRequest::class);
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return CompanySetting::getValue($this->id, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        CompanySetting::setValue($this->id, $key, $value);
    }
}
