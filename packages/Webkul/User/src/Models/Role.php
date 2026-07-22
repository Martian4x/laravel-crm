<?php

namespace Webkul\User\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Contracts\Role as RoleContract;

class Role extends Model implements RoleContract
{
    use BelongsToTenant;

    /**
     * Rows with NULL tenant_id are platform defaults visible to every tenant.
     *
     * @var bool
     */
    public static $sharesPlatformDefaults = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'permission_type',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the users.
     */
    public function users()
    {
        return $this->hasMany(UserProxy::modelClass());
    }
}
