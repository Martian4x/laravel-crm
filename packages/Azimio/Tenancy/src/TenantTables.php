<?php

namespace Azimio\Tenancy;

class TenantTables
{
    /**
     * Tables whose rows belong to exactly one tenant.
     *
     * @var array
     */
    public const STRICT = [
        'activities',
        'core_config',
        'emails',
        'groups',
        'leads',
        'marketing_campaigns',
        'marketing_events',
        'organizations',
        'persons',
        'products',
        'quotes',
        'tags',
        'users',
        'warehouses',
        'web_forms',
        'webhooks',
        'workflows',
    ];

    /**
     * Tables where NULL tenant_id rows are platform defaults visible to
     * every tenant (default pipelines, sources, roles, ...).
     *
     * @var array
     */
    public const SHARED = [
        'attributes',
        'email_templates',
        'lead_pipelines',
        'lead_sources',
        'lead_types',
        'roles',
    ];
}
