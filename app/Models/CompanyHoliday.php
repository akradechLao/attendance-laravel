<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHoliday extends Model
{
    protected $table = 'company_holidays';

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'year',
    ];

    protected $casts = [
        'date' => 'date',
        'year' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
