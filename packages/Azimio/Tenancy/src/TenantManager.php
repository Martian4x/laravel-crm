<?php

namespace Azimio\Tenancy;

use Azimio\Tenancy\Models\Tenant;

class TenantManager
{
    /**
     * The currently active tenant, if any.
     */
    protected ?Tenant $tenant = null;

    /**
     * Set the active tenant for the rest of the request/process.
     */
    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Clear the active tenant (queries become unscoped).
     */
    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * The active tenant model, or null when running unscoped
     * (super admin, console, queue worker without context).
     */
    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * The active tenant id, or null when unscoped.
     */
    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Run a callback in the context of the given tenant, restoring
     * the previous context afterwards.
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;

        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
