<?php
namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHoliday extends Model
{
    use HasCompanyScope;

    protected $table = 'company_holidays';

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'type',
        'year',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'year' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
