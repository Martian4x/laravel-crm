<?php

namespace Webkul\Attribute\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Contracts\Attribute as AttributeContract;

class Attribute extends Model implements AttributeContract
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
        'code',
        'name',
        'type',
        'entity_type',
        'lookup_type',
        'is_required',
        'is_unique',
        'quick_add',
        'validation',
        'is_user_defined',
    ];

    /**
     * Get the options.
     */
    public function options()
    {
        return $this->hasMany(AttributeOptionProxy::modelClass());
    }
}
