<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementDismissal extends Model
{
    protected $table = 'announcement_dismissals';

    protected $fillable = [
        'employee_id',
        'announcement_id',
    ];
}
