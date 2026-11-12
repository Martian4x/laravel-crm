<?php

use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\MergeFields;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

/**
 * Phase 8.7 — compose a single SMS.
 *
 * The preview endpoint is the authoritative counter: the page shows
 * whatever it returns, so these tests pin the numbers a user sees against
 * what the pipeline will actually charge.
 */
function composeAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function composeTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function composePlatformProfile(): SenderProfile
{
    return SenderProfile::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'sender_name' => 'AZIMIOCOMPOSE'],
        ['label' => 'Platform', 'driver' => 'log', 'status' => 'approved'],
    );
}

afterEach(function () {
    $tenant = composeTenant();

    foreach ([Message::class, Batch::class, CreditLedgerEntry::class, OptOut::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
    }

    Person::withoutTenancy()->where('name', 'like', 'Compose Test%')->forceDelete();

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOCOMPOSE')->forceDelete();

    Tenant::withoutGlobalScopes()->whereKey($tenant->id)->update(['sms_credits' => 0]);
});

it('renders the compose page', function () {
    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->get(route('admin.sms.compose.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.compose.message'));
});

it('returns the authoritative segment and credit count', function () {
    composePlatformProfile();
    app(CreditLedger::class)->allocate(composeTenant(), 40);

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), [
            'body'   => str_repeat('a', 161),
            'number' => '0712345678',
        ])
        ->assertOK()
        ->assertJson([
            'length'       => 161,
            'segments'     => 2,
            'credits'      => 2,
            'encoding'     => 'gsm7',
            'is_unicode'   => false,
            'uses_credits' => true,
            'balance'      => 40,
            'number'       => '+255712345678',
            'opted_out'    => false,
        ]);
});

it('flags a unicode body in the preview', function () {
    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), [
            'body' => "Smart \u{201C}quotes\u{201D}",
        ])
        ->assertOK()
        ->assertJson(['encoding' => 'ucs2', 'is_unicode' => true]);
});

it('reports a bad number in the preview rather than at send time', function () {
    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), ['number' => '0222110000'])
        ->assertOK()
        ->assertJson([
            'number'       => null,
            'number_error' => trans('messaging::app.sms.phone.not-mobile'),
        ]);
});

it('warns in the preview that a number has opted out', function () {
    composePlatformProfile();

    app(TenantManager::class)->set(composeTenant());
    OptOut::create(['dest_addr' => '+255712345678', 'source' => 'manual']);
    app(TenantManager::class)->forget();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), ['number' => '0712 345 678'])
        ->assertOK()
        ->assertJson(['opted_out' => true]);
});

it('renders merge fields against the actual recipient', function () {
    app(TenantManager::class)->set(composeTenant());

    $person = Person::create([
        'name'            => 'Compose Test Amina Juma',
        'emails'          => [['value' => 'amina@example.test', 'label' => 'work']],
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    app(TenantManager::class)->forget();

    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), [
            'body' => 'Habari {{person.first_name}}, karibu.',
            'type' => Person::class,
            'id'   => $person->id,
        ])
        ->assertOK()
        ->assertJson(['preview' => 'Habari Compose, karibu.']);
});

it('renders an unknown placeholder as empty, never as raw syntax', function () {
    // Sending literal {{person.first_name}} to a customer is worse than a
    // slightly awkward sentence, and costs the same either way.
    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), [
            'body' => 'Hello {{person.first_name}}{{nope.missing}}!',
        ])
        ->assertOK()
        ->assertJson(['preview' => 'Hello !']);
});

it('prices the merged text, not the template', function () {
    // A merged name changes the length and therefore the price, so the
    // count must be taken after rendering.
    app(TenantManager::class)->set(composeTenant());

    $person = Person::create([
        'name'            => 'Compose Test '.str_repeat('A', 200),
        'contact_numbers' => [['value' => '0712345678', 'label' => 'work']],
    ]);

    app(TenantManager::class)->forget();

    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->postJson(route('admin.sms.compose.preview'), [
            'body' => 'Hi {{person.name}}',
            'type' => Person::class,
            'id'   => $person->id,
        ])
        ->assertOK()
        // The template alone is well under one segment; the merged text is not.
        ->assertJson(['segments' => 2]);
});

it('sends a single message and records it', function () {
    composePlatformProfile();
    app(CreditLedger::class)->allocate(composeTenant(), 20);

    test()->actingAs(composeAdmin())
        ->post(route('admin.sms.compose.send'), [
            'body'   => 'Habari yako',
            'number' => '0712345678',
        ])
        ->assertRedirect(route('admin.sms.compose.index'))
        ->assertSessionHas('success');

    $message = Message::withoutTenancy()->where('tenant_id', composeTenant()->id)->first();

    expect($message)->not->toBeNull()
        ->and($message->dest_addr)->toBe('+255712345678')
        ->and($message->body)->toBe('Habari yako');
});

it('refuses to send to an opted-out number', function () {
    composePlatformProfile();
    app(CreditLedger::class)->allocate(composeTenant(), 20);

    app(TenantManager::class)->set(composeTenant());
    OptOut::create(['dest_addr' => '+255712345678', 'source' => 'manual']);
    app(TenantManager::class)->forget();

    test()->actingAs(composeAdmin())
        ->post(route('admin.sms.compose.send'), [
            'body'   => 'Habari',
            'number' => '0712345678',
        ])
        ->assertSessionHas('error', trans('messaging::app.sms.errors.opted-out'));

    expect(Message::withoutTenancy()->where('tenant_id', composeTenant()->id)->count())->toBe(0);
});

it('refuses to send without enough credits', function () {
    composePlatformProfile();

    test()->actingAs(composeAdmin())
        ->post(route('admin.sms.compose.send'), [
            'body'   => 'Habari',
            'number' => '0712345678',
        ])
        ->assertSessionHas('error');

    expect(Message::withoutTenancy()->where('tenant_id', composeTenant()->id)->count())->toBe(0);
});

it('offers the merge fields it can actually resolve', function () {
    expect(array_keys(MergeFields::available()))
        ->toContain('person.first_name', 'lead.title', 'tenant.name');
});

it('does not serve compose to a guest', function () {
    test()->get(route('admin.sms.compose.index'))->assertRedirect();
});
