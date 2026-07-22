<?php

namespace Azimio\Tenancy\Concerns;

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantScope;

trait BelongsToTenant
{
    /**
     * Register the tenant global scope and auto-stamp tenant_id on create.
     */
    public static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (is_null($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', tenant_id());
            }
        });

        /**
         * Platform-default rows (NULL tenant_id on shared tables) are
         * read-only for tenants: without this, one tenant could edit or
         * delete a default pipeline/role/source for every other tenant.
         * Returning false aborts the operation.
         */
        static::updating(function ($model) {
            if (tenant_id() && is_null($model->getOriginal('tenant_id'))) {
                return false;
            }
        });

        static::deleting(function ($model) {
            if (tenant_id() && is_null($model->getOriginal('tenant_id'))) {
                return false;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Escape hatch for super-admin/cross-tenant queries:
     * Model::withoutTenancy()->...
     */
    public function scopeWithoutTenancy($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
