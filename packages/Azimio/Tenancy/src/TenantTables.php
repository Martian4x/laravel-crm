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
        'sms_batches',
        'sms_credit_ledger',
        'sms_credit_requests',
        'sms_messages',
        'sms_opt_outs',
        'sms_sender_profiles',
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
        'sms_templates',
    ];

    /**
     * Tables with no tenant_id at all, read only by platform admins.
     *
     * sms_webhook_events is deliberately not in either list above: a provider
     * callback arrives before any tenant context exists, and is attributed to
     * a tenant only once its message_id resolves. Listing it as SHARED would
     * expose every tenant's callbacks to every other tenant.
     *
     * @var array
     */
    public const PLATFORM_ONLY = [
        'sms_webhook_events',
    ];
}
