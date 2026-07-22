<?php

namespace Azimio\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    public const STATUS_ACTIVE = 1;

    public const STATUS_SUSPENDED = 0;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'primary_color',
        'status',
        'locale',
        'currency',
        'plan',
        'max_users',
    ];

    protected $casts = [
        'status'    => 'integer',
        'max_users' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
