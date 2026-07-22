<?php

namespace Webkul\Lead\Models;

use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Source as SourceContract;

class Source extends Model implements SourceContract
{
    use BelongsToTenant;

    /**
     * Rows with NULL tenant_id are platform defaults visible to every tenant.
     *
     * @var bool
     */
    public static $sharesPlatformDefaults = true;

    protected $table = 'lead_sources';

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
        return $this->hasMany(LeadProxy::modelClass(), 'lead_source_id', 'id');
    }
}
