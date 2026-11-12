<?php

use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

/**
 * Phase 8.8 — bulk send.
 *
 * The confirmation step is the point of this feature: billing is per
 * segment (8.0.1), so a 2-segment body to 4,000 people is 8,000 credits.
 * These tests pin that nothing is written or charged before the final POST,
 * and that the figure confirmed is the figure charged.
 */
function bulkAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function bulkTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function bulkProfile(): SenderProfile
{
    return SenderProfile::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'sender_name' => 'AZIMIOBULK'],
        ['label' => 'Platform', 'driver' => 'log', 'status' => 'approved'],
    );
}

afterEach(function () {
    $tenant = bulkTenant();

    foreach ([Message::class, Batch::class, CreditLedgerEntry::class, OptOut::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
    }

    Person::withoutTenancy()->where('name', 'like', 'Bulk Test%')->forceDelete();

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOBULK')->forceDelete();

    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0]);
});

function bulkPerson(string $name, string $number): Person
{
    app(TenantManager::class)->set(bulkTenant());

    $person = Person::create([
        'name'            => $name,
        'contact_numbers' => [['value' => $number, 'label' => 'work']],
    ]);

    app(TenantManager::class)->forget();

    return $person;
}

it('renders the bulk compose page', function () {
    bulkProfile();

    test()->actingAs(bulkAdmin())
        ->get(route('admin.sms.bulk.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.bulk.review'));
});

it('shows the full cost breakdown before anything is sent', function () {
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.confirm'), [
            'body'     => str_repeat('a', 161), // two segments
            'category' => 'transactional',
            'numbers'  => "0712345678\n0713345678\n0714345678",
        ])
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.bulk.confirm-title'))
        // 3 recipients x 2 segments.
        ->assertSee('6');

    // Crucially: nothing written yet.
    expect(Message::withoutTenancy()->where('tenant_id', bulkTenant()->id)->count())->toBe(0)
        ->and(app(CreditLedger::class)->balance(bulkTenant()))->toBe(100);
});

it('lists duplicates, opt-outs and invalid numbers as exclusions', function () {
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    app(TenantManager::class)->set(bulkTenant());
    OptOut::create(['dest_addr' => '+255713345678', 'source' => 'manual']);
    app(TenantManager::class)->forget();

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.confirm'), [
            'body'     => 'Habari',
            'category' => 'marketing',
            'numbers'  => "0712345678\n+255 712 345 678\n0713345678\n0222110000\nrubbish",
        ])
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.bulk.excluded-duplicates'))
        ->assertSee(trans('messaging::app.sms.bulk.excluded-optouts'))
        ->assertSee(trans('messaging::app.sms.bulk.excluded-invalid'))
        // The landline reason is named, not just counted.
        ->assertSee(trans('messaging::app.sms.phone.not-mobile'));
});

it('sends the batch and charges what was confirmed', function () {
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.send'), [
            'body'              => 'Habari yako',
            'category'          => 'transactional',
            'name'              => 'November campaign',
            'numbers'           => "0712345678\n0713345678",
            'confirmed_credits' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batch = Batch::withoutTenancy()->where('tenant_id', bulkTenant()->id)->latest('id')->first();

    expect($batch)->not->toBeNull()
        ->and($batch->name)->toBe('November campaign')
        ->and($batch->total_recipients)->toBe(2)
        ->and($batch->credits_estimated)->toBe(2)
        ->and(app(CreditLedger::class)->balance(bulkTenant()))->toBe(98);
});

it('refuses to send when the cost no longer matches what was confirmed', function () {
    // The guard against a stale confirmation screen: the audience or an
    // opt-out may have changed, and nobody should be charged a figure they
    // did not agree to.
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.send'), [
            'body'              => 'Habari',
            'category'          => 'transactional',
            'numbers'           => "0712345678\n0713345678\n0714345678",
            'confirmed_credits' => 2, // stale: it is really 3
        ])
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.bulk.confirm-title'))
        ->assertSessionHas('error');

    expect(Message::withoutTenancy()->where('tenant_id', bulkTenant()->id)->count())->toBe(0)
        ->and(app(CreditLedger::class)->balance(bulkTenant()))->toBe(100);
});

it('blocks a send the balance cannot cover', function () {
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 1);

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.send'), [
            'body'              => 'Habari',
            'category'          => 'transactional',
            'numbers'           => "0712345678\n0713345678\n0714345678",
            'confirmed_credits' => 3,
        ])
        ->assertRedirect(route('admin.sms.credits.index'))
        ->assertSessionHas('error');

    expect(Message::withoutTenancy()->where('tenant_id', bulkTenant()->id)->count())->toBe(0);
});

it('builds the audience from selected contacts', function () {
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    $a = bulkPerson('Bulk Test Amina', '0712345678');
    $b = bulkPerson('Bulk Test Baraka', '0713345678');

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.from_selection', ['entity' => 'person']), [
            'indexes' => [$a->id, $b->id],
        ])
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.bulk.title'));

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.send'), [
            'body'              => 'Habari {{person.first_name}}',
            'category'          => 'transactional',
            'person_ids'        => [$a->id, $b->id],
            'confirmed_credits' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $messages = Message::withoutTenancy()->where('tenant_id', bulkTenant()->id)->get();

    expect($messages)->toHaveCount(2)
        // Each message is linked back to the contact it went to.
        ->and($messages->pluck('recipient_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('ignores contact ids that belong to another tenant', function () {
    // A tampered form must not reach another company's contacts: Person is
    // tenant-scoped, so the ids simply do not resolve.
    bulkProfile();
    app(CreditLedger::class)->allocate(bulkTenant(), 100);

    $other = Tenant::withoutGlobalScopes()->where('slug', 'kilimanjaro-suppliers')->first();

    app(TenantManager::class)->set($other);
    $foreign = Person::create([
        'name'            => 'Bulk Test Foreign',
        'contact_numbers' => [['value' => '0719999999', 'label' => 'work']],
    ]);
    app(TenantManager::class)->forget();

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.confirm'), [
            'body'       => 'Habari',
            'category'   => 'transactional',
            'person_ids' => [$foreign->id],
        ])
        ->assertSessionHas('error', trans('messaging::app.sms.bulk.no-audience'));

    expect(Message::withoutTenancy()->where('dest_addr', '+255719999999')->count())->toBe(0);
});

it('requires a body', function () {
    bulkProfile();

    test()->actingAs(bulkAdmin())
        ->post(route('admin.sms.bulk.confirm'), ['category' => 'transactional', 'numbers' => '0712345678'])
        ->assertSessionHasErrors('body');
});

it('does not serve bulk send to a guest', function () {
    test()->get(route('admin.sms.bulk.index'))->assertRedirect();
});
