<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'admin_users';

    protected $fillable = [
        'company_id',
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
