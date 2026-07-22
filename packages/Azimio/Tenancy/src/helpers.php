<?php

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;

if (! function_exists('tenant')) {
    /**
     * The active tenant, or null when running unscoped.
     */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->get();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * The active tenant id, or null when running unscoped.
     */
    function tenant_id(): ?int
    {
        return app(TenantManager::class)->id();
    }
}
