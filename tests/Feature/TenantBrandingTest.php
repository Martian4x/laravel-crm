<?php

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

afterEach(function () {
    DB::table('core_config')
        ->where('code', 'general.settings.menu_color.brand_color')
        ->whereNotNull('tenant_id')
        ->delete();
});

it('shows the tenant name in the page title for tenant users', function () {
    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@kilimanjaro-suppliers.test')->first();

    test()->actingAs($tenantAdmin)
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertSee('Kilimanjaro Suppliers Co');
});

it('applies the tenant brand color from tenant-scoped config', function () {
    $tenant = Tenant::where('slug', 'kilimanjaro-suppliers')->first();

    DB::table('core_config')->insert([
        'code'       => 'general.settings.menu_color.brand_color',
        'value'      => '#1a7f37',
        'tenant_id'  => $tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@kilimanjaro-suppliers.test')->first();

    test()->actingAs($tenantAdmin)
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertSee('--brand-color: #1a7f37', false);
});

it('does not leak one tenant\'s brand color to another tenant', function () {
    $tenant = Tenant::where('slug', 'kilimanjaro-suppliers')->first();

    DB::table('core_config')->insert([
        'code'       => 'general.settings.menu_color.brand_color',
        'value'      => '#1a7f37',
        'tenant_id'  => $tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($otherAdmin)
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertSee('--brand-color: #0E90D9', false);
});
