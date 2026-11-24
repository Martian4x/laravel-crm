<?php

use Azimio\Services\Enums\ServiceUnit;
use Azimio\Services\Models\Service;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Webkul\User\Models\User;

/**
 * Phase 9.4 — the Services module.
 *
 * Services are a tenant's own price list, so the tests that matter most are
 * the isolation ones: Phase 3 established that a module which skips tenant
 * scoping is far more expensive to fix later than to build correctly now.
 */
function svcAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function svcTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function svcOtherTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'kilimanjaro-suppliers')->firstOrFail();
}

function makeService(array $attributes = [], ?int $tenantId = null): Service
{
    $service = new Service;

    $service->forceFill(array_merge([
        'tenant_id'  => $tenantId ?? svcTenant()->id,
        'name'       => 'Consulting',
        'price'      => 150000,
        'unit'       => ServiceUnit::Hour->value,
        'is_taxable' => true,
        'is_active'  => true,
    ], $attributes))->save();

    return $service;
}

afterEach(function () {
    Service::withoutTenancy()->whereIn('tenant_id', [svcTenant()->id, svcOtherTenant()->id])->delete();
});

it('lists only the current tenant services', function () {
    makeService(['name' => 'Ours']);
    makeService(['name' => 'Theirs'], svcOtherTenant()->id);

    test()->actingAs(svcAdmin())
        ->get(route('admin.services.index'))
        ->assertOK()
        ->assertSee('Ours')
        ->assertDontSee('Theirs');
});

it('creates a service for the acting tenant', function () {
    test()->actingAs(svcAdmin())
        ->post(route('admin.services.store'), [
            'name'        => 'Site Installation',
            'code'        => 'INST-01',
            'description' => 'On-site setup',
            'price'       => '450000.00',
            'unit'        => 'fixed',
            'is_taxable'  => '1',
            'is_active'   => '1',
        ])
        ->assertRedirect(route('admin.services.index'));

    $service = Service::withoutTenancy()->where('code', 'INST-01')->first();

    expect($service)->not->toBeNull()
        ->and($service->tenant_id)->toBe(svcTenant()->id)
        ->and($service->unit)->toBe(ServiceUnit::Fixed)
        ->and((float) $service->price)->toBe(450000.0)
        ->and($service->is_taxable)->toBeTrue();
});

it('creates a service when the optional fields are omitted', function () {
    // The nullable-key crash again: an absent field is MISSING from the
    // validated array, not null. Shipped on `locale`, a bulk-send `name`,
    // tenant `currency` and the SMS credentials.
    test()->actingAs(svcAdmin())
        ->post(route('admin.services.store'), [
            'name'  => 'Bare Minimum',
            'price' => '1000',
            'unit'  => 'day',
        ])
        ->assertRedirect(route('admin.services.index'));

    $service = Service::withoutTenancy()->where('name', 'Bare Minimum')->first();

    expect($service)->not->toBeNull()
        ->and($service->code)->toBeNull()
        ->and($service->description)->toBeNull()
        // Unchecked boxes submit 0 through the hidden companion field.
        ->and($service->is_taxable)->toBeFalse();
});

it('refuses to edit another tenant service', function () {
    $theirs = makeService(['name' => 'Theirs'], svcOtherTenant()->id);

    test()->actingAs(svcAdmin())
        ->get(route('admin.services.edit', $theirs->id))
        ->assertNotFound();
});

it('refuses to delete another tenant service', function () {
    $theirs = makeService(['name' => 'Theirs'], svcOtherTenant()->id);

    test()->actingAs(svcAdmin())
        ->post(route('admin.services.delete', $theirs->id))
        ->assertNotFound();

    expect(Service::withoutTenancy()->find($theirs->id))->not->toBeNull();
});

it('updates a service', function () {
    $service = makeService(['name' => 'Old Name']);

    test()->actingAs(svcAdmin())
        ->put(route('admin.services.update', $service->id), [
            'name'  => 'New Name',
            'price' => '200000',
            'unit'  => 'month',
        ])
        ->assertRedirect(route('admin.services.index'));

    $service->refresh();

    expect($service->name)->toBe('New Name')
        ->and($service->unit)->toBe(ServiceUnit::Month);
});

it('retires a service instead of losing it', function () {
    // A retired service must stay in the table: a past quote that referenced
    // it has to remain resolvable.
    $service = makeService();

    test()->actingAs(svcAdmin())
        ->post(route('admin.services.toggle', $service->id))
        ->assertRedirect();

    expect($service->fresh()->is_active)->toBeFalse()
        ->and(Service::withoutTenancy()->find($service->id))->not->toBeNull();
});

it('keeps retired services out of the active scope', function () {
    makeService(['name' => 'Live']);
    makeService(['name' => 'Retired', 'is_active' => false]);

    app(TenantManager::class)->runAs(svcTenant(), function () {
        $names = Service::active()->pluck('name')->all();

        expect($names)->toContain('Live')->not->toContain('Retired');
    });
});

it('lets two tenants use the same service code', function () {
    makeService(['code' => 'CONSULT-01']);

    // The unique index is (tenant_id, code), so this must not collide.
    $theirs = makeService(['code' => 'CONSULT-01'], svcOtherTenant()->id);

    expect($theirs->id)->toBeGreaterThan(0);
});

it('rejects a duplicate code within one tenant', function () {
    makeService(['code' => 'DUP-01']);

    test()->actingAs(svcAdmin())
        ->post(route('admin.services.store'), [
            'name'  => 'Another',
            'code'  => 'DUP-01',
            'price' => '1000',
            'unit'  => 'fixed',
        ])
        ->assertSessionHasErrors('code');
});

it('rejects an unknown billing unit', function () {
    test()->actingAs(svcAdmin())
        ->post(route('admin.services.store'), [
            'name'  => 'Bad Unit',
            'price' => '1000',
            'unit'  => 'fortnight',
        ])
        ->assertSessionHasErrors('unit');
});

it('renders the create and edit forms', function () {
    // Rendered, not merely compiled -- a Blade page can compile cleanly and
    // still explode at render time, which has happened on this project.
    test()->actingAs(svcAdmin())
        ->get(route('admin.services.create'))
        ->assertOK()
        ->assertSee('name="price"', false);

    $service = makeService(['name' => 'Editable']);

    test()->actingAs(svcAdmin())
        ->get(route('admin.services.edit', $service->id))
        ->assertOK()
        ->assertSee('Editable');
});

it('does not serve services to a guest', function () {
    test()->get(route('admin.services.index'))->assertRedirect();
});

it('prices a service with its unit', function () {
    expect(makeService(['price' => 150000, 'unit' => 'hour'])->priceLabel())
        ->toContain('150,000.00')
        ->and(makeService(['price' => 90000, 'unit' => 'fixed', 'code' => 'F1'])->priceLabel())
        ->toBe('90,000.00');
});
