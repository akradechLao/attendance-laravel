<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceScanLog extends Model
{
    protected $table = 'face_scan_logs';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'scan_type',
        'match_score',
        'is_verified',
        'created_at',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
