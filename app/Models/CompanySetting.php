<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_id',
        'key',
        'value',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function getValue(int $companyId, string $key, mixed $default = null): mixed
    {
        $setting = self::where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue(int $companyId, string $key, mixed $value): self
    {
        return self::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => $value]
        );
    }
}
