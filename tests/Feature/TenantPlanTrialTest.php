<?php

use Azimio\Tenancy\Models\Tenant;
use Webkul\User\Models\User;

/**
 * Phase 7.9 / 7.5 — plan and trial editing, suspension reasons, and the
 * trial-expiry sweep.
 *
 * The point of recording a reason: "suspended" as a bare boolean leaves the
 * next admin — and the tenant on the phone asking why — unable to tell
 * non-payment from abuse from a lapsed trial from a mis-click.
 */
function planSuper(): User
{
    return User::withoutTenancy()->where('is_super_admin', 1)->firstOrFail();
}

function planTenant(): Tenant
{
    $tenant = Tenant::withoutGlobalScopes()->firstOrCreate(
        ['slug' => 'plan-trial-test'],
        ['name' => 'Plan Trial Test']
    );

    $tenant->forceFill(['activated_at' => now()])->save();

    return $tenant->fresh();
}

afterEach(function () {
    Tenant::withoutGlobalScopes()->where('slug', 'plan-trial-test')->delete();
});

it('reports a clear state rather than a bare boolean', function () {
    $tenant = planTenant();

    expect($tenant->state())->toBe('active');

    $tenant->forceFill(['trial_ends_at' => now()->addDays(5)])->save();
    expect($tenant->fresh()->state())->toBe('trial');

    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();
    expect($tenant->fresh()->state())->toBe('trial-expired');

    $tenant->forceFill(['activated_at' => null])->save();
    expect($tenant->fresh()->state())->toBe('pending');

    $tenant->forceFill(['status' => Tenant::STATUS_SUSPENDED])->save();
    // Suspension outranks everything: it is the thing to act on.
    expect($tenant->fresh()->state())->toBe('suspended');
});

it('lets a super admin edit the plan, user cap and trial end', function () {
    $tenant = planTenant();

    test()->actingAs(planSuper())
        ->put(route('super.tenants.update', $tenant->id), [
            'name'          => 'Plan Trial Test',
            'locale'        => 'en',
            'plan'          => 'Growth',
            'max_users'     => 25,
            'trial_ends_at' => now()->addDays(30)->toDateString(),
        ])
        ->assertRedirect(route('super.tenants.index'));

    $fresh = $tenant->fresh();

    expect($fresh->plan)->toBe('Growth')
        ->and($fresh->max_users)->toBe(25)
        ->and($fresh->trial_ends_at->toDateString())->toBe(now()->addDays(30)->toDateString());
});

it('clears the trial when the field is left blank', function () {
    // On this screen a blank trial date means "no trial", not "leave it".
    $tenant = planTenant();
    $tenant->forceFill(['trial_ends_at' => now()->addDays(5)])->save();

    test()->actingAs(planSuper())
        ->put(route('super.tenants.update', $tenant->id), [
            'name'   => 'Plan Trial Test',
            'locale' => 'en',
        ])
        ->assertRedirect(route('super.tenants.index'));

    expect($tenant->fresh()->trial_ends_at)->toBeNull();
});

it('records why a tenant was suspended, and clears it on reactivation', function () {
    $tenant = planTenant();

    test()->actingAs(planSuper())
        ->post(route('super.tenants.toggle', $tenant->id), ['reason' => 'Invoice unpaid 60 days'])
        ->assertRedirect(route('super.tenants.index'));

    $suspended = $tenant->fresh();

    expect($suspended->isActive())->toBeFalse()
        ->and($suspended->suspension_reason)->toBe('Invoice unpaid 60 days')
        ->and($suspended->suspended_at)->not->toBeNull();

    test()->actingAs(planSuper())->post(route('super.tenants.toggle', $tenant->id));

    // A stale reason must not hang around on a working account.
    expect($tenant->fresh()->isActive())->toBeTrue()
        ->and($tenant->fresh()->suspension_reason)->toBeNull()
        ->and($tenant->fresh()->suspended_at)->toBeNull();
});

it('records a placeholder when no reason is given', function () {
    $tenant = planTenant();

    test()->actingAs(planSuper())->post(route('super.tenants.toggle', $tenant->id));

    expect($tenant->fresh()->suspension_reason)->toBe(trans('azimio::app.tenants.no-reason-given'));
});

it('shows the state and reason on the edit page', function () {
    $tenant = planTenant();
    $tenant->forceFill([
        'status'            => Tenant::STATUS_SUSPENDED,
        'suspended_at'      => now(),
        'suspension_reason' => 'Abuse report',
    ])->save();

    test()->actingAs(planSuper())
        ->get(route('super.tenants.edit', $tenant->id))
        ->assertOK()
        ->assertSee(trans('azimio::app.tenants.state-suspended'))
        ->assertSee('Abuse report');
});

it('suspends an expired trial and says why', function () {
    $tenant = planTenant();
    $tenant->forceFill(['trial_ends_at' => now()->subDays(2)])->save();

    test()->artisan('azimio:tenant:expire-trials')->assertSuccessful();

    $fresh = $tenant->fresh();

    expect($fresh->isActive())->toBeFalse()
        ->and($fresh->suspension_reason)->toContain('Trial ended')
        ->and($fresh->suspended_at)->not->toBeNull();
});

it('leaves a live trial and a paid plan alone', function () {
    $live = planTenant();
    $live->forceFill(['trial_ends_at' => now()->addDays(3)])->save();

    test()->artisan('azimio:tenant:expire-trials')->assertSuccessful();

    expect($live->fresh()->isActive())->toBeTrue();

    // No trial date at all means a real plan, never touched.
    $live->forceFill(['trial_ends_at' => null])->save();

    test()->artisan('azimio:tenant:expire-trials')->assertSuccessful();

    expect($live->fresh()->isActive())->toBeTrue();
});

it('reports without changing anything on a dry run', function () {
    $tenant = planTenant();
    $tenant->forceFill(['trial_ends_at' => now()->subDay()])->save();

    test()->artisan('azimio:tenant:expire-trials --dry-run')->assertSuccessful();

    expect($tenant->fresh()->isActive())->toBeTrue();
});
