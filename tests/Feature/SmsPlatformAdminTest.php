<?php

use Azimio\Messaging\Enums\CreditType;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Webkul\User\Models\User;

/**
 * Phase 8.14 / 8.15 — platform SMS control.
 *
 * The super panel reads across every tenant, so these also check the
 * obvious failure mode: a tenant user must not reach any of it.
 */
function superAdmin(): User
{
    return User::withoutTenancy()->where('is_super_admin', 1)->firstOrFail();
}

function tenantUser(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function twigaTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

afterEach(function () {
    CreditLedgerEntry::withoutTenancy()->where('note', 'like', 'PLATFORM TEST%')->forceDelete();

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->whereIn('sender_name', ['PLATTEST', 'AZIMIOCRM'])->forceDelete();

    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0]);
});

it('renders the platform SMS page for a super admin', function () {
    test()->actingAs(superAdmin())
        ->get(route('super.sms.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.super.allocate-title'));
});

it('hides the platform SMS page from a tenant user', function () {
    test()->actingAs(tenantUser())
        ->get(route('super.sms.index'))
        ->assertNotFound();
});

it('hides credit allocation from a tenant user', function () {
    // The important one: a tenant must not be able to grant themselves
    // credits by posting to the super endpoint.
    test()->actingAs(tenantUser())
        ->post(route('super.sms.allocate'), [
            'tenant_id' => twigaTenant()->id,
            'credits'   => 9999,
            'type'      => 'allocation',
            'note'      => 'PLATFORM TEST self-grant',
        ])
        ->assertNotFound();

    expect(app(CreditLedger::class)->balance(twigaTenant()))->toBe(0);
});

it('saves the platform sender account', function () {
    test()->actingAs(superAdmin())
        ->post(route('super.sms.store'), [
            'driver'      => 'log',
            'sender_name' => 'PLATTEST',
            'api_key'     => 'platform-key',
            'secret_key'  => 'platform-secret',
        ])
        ->assertRedirect(route('super.sms.index'))
        ->assertSessionHas('success');

    $profile = SenderProfile::platform();

    expect($profile)->not->toBeNull()
        ->and($profile->tenant_id)->toBeNull()
        ->and($profile->sender_name)->toBe('PLATTEST')
        ->and($profile->credentials['api_key'])->toBe('platform-key');
});

it('keeps the stored secret when the field is left blank', function () {
    test()->actingAs(superAdmin())->post(route('super.sms.store'), [
        'driver'  => 'log', 'sender_name' => 'PLATTEST',
        'api_key' => 'original-key', 'secret_key' => 'original-secret',
    ]);

    // Editing only the sender name must not wipe the credentials.
    test()->actingAs(superAdmin())->post(route('super.sms.store'), [
        'driver' => 'log', 'sender_name' => 'PLATTEST', 'api_key' => '', 'secret_key' => '',
    ]);

    expect(SenderProfile::platform()->credentials['api_key'])->toBe('original-key');
});

it('allocates credits with an audit note', function () {
    test()->actingAs(superAdmin())
        ->post(route('super.sms.allocate'), [
            'tenant_id' => twigaTenant()->id,
            'credits'   => 500,
            'type'      => 'purchase',
            'note'      => 'PLATFORM TEST mobile money ref 12345',
        ])
        ->assertRedirect(route('super.sms.index'))
        ->assertSessionHas('success');

    $entry = CreditLedgerEntry::withoutTenancy()->latest('id')->first();

    expect(app(CreditLedger::class)->balance(twigaTenant()))->toBe(500)
        ->and($entry->type)->toBe(CreditType::Purchase)
        ->and($entry->credits)->toBe(500)
        ->and($entry->note)->toContain('mobile money ref 12345')
        // Who did it matters in a dispute.
        ->and($entry->created_by)->toBe(superAdmin()->id);
});

it('requires a note on every allocation', function () {
    test()->actingAs(superAdmin())
        ->post(route('super.sms.allocate'), [
            'tenant_id' => twigaTenant()->id,
            'credits'   => 100,
            'type'      => 'allocation',
        ])
        ->assertSessionHasErrors('note');
});

it('refuses a deduction that would take the balance negative', function () {
    // A company cannot owe credits, and a negative balance would make every
    // reservation fail in a way nobody could explain.
    app(CreditLedger::class)->allocate(twigaTenant(), 50, null, 'PLATFORM TEST seed');

    test()->actingAs(superAdmin())
        ->post(route('super.sms.allocate'), [
            'tenant_id' => twigaTenant()->id,
            'credits'   => -100,
            'type'      => 'adjustment',
            'note'      => 'PLATFORM TEST overdraw',
        ])
        ->assertSessionHas('error');

    expect(app(CreditLedger::class)->balance(twigaTenant()))->toBe(50);
});

it('allows a deduction within the balance', function () {
    app(CreditLedger::class)->allocate(twigaTenant(), 50, null, 'PLATFORM TEST seed');

    test()->actingAs(superAdmin())
        ->post(route('super.sms.allocate'), [
            'tenant_id' => twigaTenant()->id,
            'credits'   => -20,
            'type'      => 'adjustment',
            'note'      => 'PLATFORM TEST correction',
        ])
        ->assertSessionHas('success');

    expect(app(CreditLedger::class)->balance(twigaTenant()))->toBe(30);
});

it('renders a tenant ledger and reconciles drift', function () {
    $ledger = app(CreditLedger::class);

    $ledger->allocate(twigaTenant(), 100, null, 'PLATFORM TEST seed');

    // Simulate an abandoned reservation: credits held, no ledger row.
    $ledger->reserve(twigaTenant(), 40);

    test()->actingAs(superAdmin())
        ->get(route('super.sms.ledger', twigaTenant()->id))
        ->assertOK()
        ->assertSee(trans('messaging::app.super.drift-help'));

    test()->actingAs(superAdmin())
        ->post(route('super.sms.reconcile', twigaTenant()->id))
        ->assertRedirect(route('super.sms.ledger', twigaTenant()->id))
        ->assertSessionHas('success');

    expect($ledger->balance(twigaTenant()))->toBe(100);
});

it('renders the cross-tenant message log', function () {
    test()->actingAs(superAdmin())
        ->get(route('super.sms.messages'))
        ->assertOK();
});
