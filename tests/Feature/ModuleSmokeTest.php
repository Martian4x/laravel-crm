<?php

use Webkul\User\Models\User;

/**
 * Phase 6 regression smoke: every module index page must render for both a
 * tenant admin (scoped) and the platform super admin (unscoped).
 */
function moduleRoutes(): array
{
    return [
        'admin.dashboard.index',
        'admin.leads.index',
        'admin.contacts.persons.index',
        'admin.contacts.organizations.index',
        'admin.products.index',
        'admin.quotes.index',
        'admin.activities.index',
        'admin.settings.index',
        'admin.settings.users.index',
        'admin.settings.roles.index',
        'admin.settings.groups.index',
        'admin.settings.pipelines.index',
        'admin.settings.sources.index',
        'admin.settings.types.index',
        'admin.settings.tags.index',
        'admin.settings.warehouses.index',
        'admin.settings.web_forms.index',
        'admin.settings.webhooks.index',
        'admin.settings.workflows.index',
        'admin.settings.email_templates.index',
        'admin.settings.attributes.index',
        'admin.settings.marketing.campaigns.index',
        'admin.settings.marketing.events.index',
        'admin.settings.data_transfer.imports.index',
        'admin.configuration.index',
    ];
}

it('renders every module index for a tenant admin', function () {
    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    foreach (moduleRoutes() as $route) {
        $response = test()->actingAs($tenantAdmin)->get(route($route));

        expect($response->status())
            ->toBeIn([200, 302], "Route {$route} returned {$response->status()} for tenant admin");
    }

    $response = test()->actingAs($tenantAdmin)->get(route('admin.mail.index', ['route' => 'inbox']));

    expect($response->status())->toBeIn([200, 302]);
});

it('renders every module index for the super admin', function () {
    $superAdmin = getDefaultAdmin();

    foreach (moduleRoutes() as $route) {
        $response = test()->actingAs($superAdmin)->get(route($route));

        expect($response->status())
            ->toBeIn([200, 302], "Route {$route} returned {$response->status()} for super admin");
    }
});
