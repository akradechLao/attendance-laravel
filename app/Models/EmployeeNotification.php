<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeNotification extends Model
{
    protected $table = 'employee_notifications';

    protected $fillable = [
        'emp_id',
        'type',
        'title',
        'message',
        'related_id',
        'related_type',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'emp_id');
    }

    public static function notify($empId, $type, $title, $message, $relatedId = null, $relatedType = null)
    {
        return static::create([
            'emp_id' => $empId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);
    }

    public static function notifyMultiple(array $empIds, $type, $title, $message, $relatedId = null, $relatedType = null)
    {
        $notifications = [];
        foreach ($empIds as $empId) {
            $notifications[] = static::create([
                'emp_id' => $empId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_id' => $relatedId,
                'related_type' => $relatedType,
            ]);
        }
        return $notifications;
    }
}
