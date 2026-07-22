<?php

namespace Azimio\Tenancy\Listeners;

use Azimio\Tenancy\TenantTables;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ScopeDataGridToTenant
{
    /**
     * DataGrids build their listings on the raw query builder
     * (DB::table(...)), which bypasses Eloquent global scopes. This wildcard
     * listener fires on every "datagrid.*.query_builder.set.after" event and
     * constrains the grid's root table to the active tenant.
     */
    public function handle(string $eventName, array $payload): void
    {
        if (! str_ends_with($eventName, '.query_builder.set.after')) {
            return;
        }

        $tenantId = tenant_id();

        if (! $tenantId) {
            return;
        }

        $grid = $payload[0] ?? null;

        if (! $grid || ! method_exists($grid, 'getQueryBuilder')) {
            return;
        }

        $builder = $grid->getQueryBuilder();

        // Eloquent builders already carry the TenantScope global scope.
        if ($builder instanceof EloquentBuilder || ! $builder instanceof QueryBuilder) {
            return;
        }

        $from = $builder->from;

        if (! is_string($from)) {
            return;
        }

        // "leads" or "leads as l" — filter on the alias when present.
        $parts = preg_split('/\s+as\s+/i', trim($from));
        $table = $parts[0];
        $alias = $parts[1] ?? $parts[0];

        if (in_array($table, TenantTables::STRICT, true)) {
            $builder->where($alias.'.tenant_id', $tenantId);
        } elseif (in_array($table, TenantTables::SHARED, true)) {
            $builder->where(function ($query) use ($alias, $tenantId) {
                $query->where($alias.'.tenant_id', $tenantId)
                    ->orWhereNull($alias.'.tenant_id');
            });
        }
    }
}
