<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFaceData extends Model
{
    protected $table = 'employee_face_data';

    protected $fillable = [
        'employee_id',
        'face_encoding',
        'angle',
    ];

    protected $hidden = [
        'face_encoding',
    ];

    protected $casts = [
        'face_encoding' => 'binary',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
