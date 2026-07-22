<?php

use Azimio\Tenancy\Models\Tenant;
use Webkul\User\Models\User;

afterEach(function () {
    // Never leave a dev tenant suspended after a test run.
    Tenant::query()->where('slug', 'twiga-traders')->update(['status' => Tenant::STATUS_ACTIVE]);
});

it('lets the super admin view the tenants panel', function () {
    test()->actingAs(getDefaultAdmin())
        ->get(route('super.tenants.index'))
        ->assertOK()
        ->assertSee('Twiga Traders');
});

it('hides the tenants panel from tenant users', function () {
    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($tenantAdmin)
        ->get(route('super.tenants.index'))
        ->assertNotFound();
});

it('logs out users of a suspended tenant', function () {
    $tenant = Tenant::where('slug', 'twiga-traders')->first();

    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($tenantAdmin)
        ->get(route('admin.dashboard.index'))
        ->assertRedirect(route('admin.session.create'));

    expect(auth()->guard('user')->user())->toBeNull();
});

it('lets the super admin suspend and reactivate a tenant', function () {
    $tenant = Tenant::where('slug', 'twiga-traders')->first();

    test()->actingAs(getDefaultAdmin())
        ->post(route('super.tenants.toggle', $tenant->id))
        ->assertRedirect(route('super.tenants.index'));

    expect($tenant->fresh()->isActive())->toBeFalse();

    test()->post(route('super.tenants.toggle', $tenant->id));

    expect($tenant->fresh()->isActive())->toBeTrue();
});

it('blocks user creation beyond the tenant plan limit', function () {
    $tenant = Tenant::where('slug', 'twiga-traders')->first();

    $tenant->update(['max_users' => 1]);

    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($tenantAdmin)
        ->postJson(route('admin.settings.users.store'), [
            'name'    => 'Extra User',
            'email'   => 'extra@twiga-traders.test',
            'role_id' => 1,
        ])
        ->assertStatus(422);

    expect(User::withoutTenancy()->where('email', 'extra@twiga-traders.test')->exists())->toBeFalse();

    $tenant->update(['max_users' => null]);
});

it('lets the super admin impersonate a tenant admin and revert', function () {
    $tenant = Tenant::where('slug', 'twiga-traders')->first();

    test()->actingAs(getDefaultAdmin())
        ->post(route('super.tenants.impersonate', $tenant->id))
        ->assertRedirect(route('admin.dashboard.index'));

    expect(auth()->guard('user')->user()->email)->toBe('admin@twiga-traders.test');

    test()->post(route('super.tenants.revert'))
        ->assertRedirect(route('super.tenants.index'));

    expect(auth()->guard('user')->user()->is_super_admin)->toBe(1);
});
