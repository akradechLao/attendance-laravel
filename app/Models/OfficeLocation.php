<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeLocation extends Model
{
    protected $table = 'office_locations';

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'work_start_time',
        'work_end_time',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_office_locations');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
