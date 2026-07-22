<?php

namespace Webkul\Lead\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Type as TypeContract;

class Type extends Model implements TypeContract
{
    use BelongsToTenant;

    /**
     * Rows with NULL tenant_id are platform defaults visible to every tenant.
     *
     * @var bool
     */
    public static $sharesPlatformDefaults = true;

    protected $table = 'lead_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass());
    }
}
