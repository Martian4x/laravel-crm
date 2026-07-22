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
