<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    protected $fillable = [
        "company_id",
        "group_name",
        "group_type",
        "telegram_chat_id",
        "office_location_id",
        "is_active",
    ];

    protected $casts = [
        "is_active" => "boolean",
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function officeLocation() { return $this->belongsTo(OfficeLocation::class); }
}
