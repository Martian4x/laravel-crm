<?php

namespace Azimio\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Scope every query to the active tenant. When no tenant context is set
     * (super admin, console, queue worker) queries stay unscoped.
     *
     * Models that expose platform defaults (rows with a NULL tenant_id, e.g.
     * default pipelines, sources, roles) declare
     * `public static $sharesPlatformDefaults = true;` and match their own
     * rows OR the shared platform rows.
     */
    public function apply(Builder $builder, Model $model)
    {
        $tenantId = app(TenantManager::class)->id();

        if (! $tenantId) {
            return;
        }

        $column = $model->getTable().'.tenant_id';

        $shares = property_exists($model, 'sharesPlatformDefaults') && $model::$sharesPlatformDefaults;

        if ($shares) {
            $builder->where(function ($query) use ($column, $tenantId) {
                $query->where($column, $tenantId)
                    ->orWhereNull($column);
            });
        } else {
            $builder->where($column, $tenantId);
        }
    }
}
