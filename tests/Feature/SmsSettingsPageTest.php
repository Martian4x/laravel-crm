<?php

use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Webkul\User\Models\User;

/**
 * Phase 8.6 — Settings -> SMS.
 *
 * Beyond "does it render", the tests that matter are that a tenant cannot
 * activate a provider that does not work, and that an API secret never
 * reaches the page.
 */
function smsAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function smsTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function makePlatformProfile(): SenderProfile
{
    return SenderProfile::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'sender_name' => 'AZIMIOTESTPLAT'],
        ['label' => 'Platform', 'driver' => 'log', 'status' => 'approved'],
    );
}

function saveOwnProvider(string $sender, string $key = 'key', string $secret = 'secret')
{
    return test()->actingAs(smsAdmin())->post(route('admin.sms.settings.store'), [
        'driver'      => 'log',
        'sender_name' => $sender,
        'api_key'     => $key,
        'secret_key'  => $secret,
    ]);
}

afterEach(function () {
    $tenant = smsTenant();

    foreach ([Message::class, Batch::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
    }

    SenderProfile::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOTESTPLAT')->forceDelete();

    Tenant::withoutGlobalScopes()->whereKey($tenant->id)
        ->update(['sms_mode' => 'platform', 'sms_credits' => 0]);
});

it('renders the settings page for a tenant admin', function () {
    test()->actingAs(smsAdmin())
        ->get(route('admin.sms.settings.index'))
        ->assertOK()
        ->assertSee('AZIMIOCRM')
        ->assertSee(trans('messaging::app.sms.settings.own-title'));
});

it('shows the platform credit balance', function () {
    app(CreditLedger::class)->allocate(smsTenant(), 250);

    test()->actingAs(smsAdmin())
        ->get(route('admin.sms.settings.index'))
        ->assertOK()
        ->assertSee('250');
});

it('warns when the credit balance is low', function () {
    app(CreditLedger::class)->allocate(smsTenant(), 5);

    test()->actingAs(smsAdmin())
        ->get(route('admin.sms.settings.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.settings.low-balance'));
});

it('activates an own provider whose credentials verify', function () {
    saveOwnProvider(config('messaging.platform.sender_name'))
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('success');

    $profile = SenderProfile::withoutTenancy()->where('tenant_id', smsTenant()->id)->first();

    expect($profile->status)->toBe(SenderProfileStatus::Approved)
        ->and(smsTenant()->fresh()->sms_mode)->toBe('own');
});

it('refuses to activate a sender name the provider does not know', function () {
    // An unregistered sender ID fails every send, so it must not be
    // possible to switch into that state.
    SenderProfile::withoutTenancy()->where('tenant_id', smsTenant()->id)->delete();

    saveOwnProvider('NOTREAL')
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('error');

    $profile = SenderProfile::withoutTenancy()->where('tenant_id', smsTenant()->id)->first();

    // 9.5.3 changed this: verification now runs BEFORE anything is written,
    // so a refused setup leaves NO profile rather than a Pending one. The
    // old flow saved Pending first and called the provider second, which
    // meant a bad edit downgraded a previously working sender and stopped
    // the tenant sending at all.
    expect($profile)->toBeNull()
        ->and(smsTenant()->fresh()->sms_mode)->toBe('platform');
});

it('never renders stored credentials back to the page', function () {
    saveOwnProvider(config('messaging.platform.sender_name'), 'super-secret-key', 'super-secret-secret');

    test()->actingAs(smsAdmin())
        ->get(route('admin.sms.settings.index'))
        ->assertOK()
        ->assertDontSee('super-secret-key')
        ->assertDontSee('super-secret-secret');
});

it('switches back to platform mode in one click and keeps the credentials', function () {
    saveOwnProvider(config('messaging.platform.sender_name'));

    expect(smsTenant()->fresh()->sms_mode)->toBe('own');

    test()->actingAs(smsAdmin())
        ->post(route('admin.sms.settings.platform'))
        ->assertRedirect(route('admin.sms.settings.index'));

    $profile = SenderProfile::withoutTenancy()->where('tenant_id', smsTenant()->id)->first();

    expect(smsTenant()->fresh()->sms_mode)->toBe('platform')
        // Switching back must not mean retyping an API secret.
        ->and($profile)->not->toBeNull()
        ->and($profile->credentials['api_key'])->toBe('key');
});

it('rejects an invalid test number with a reason', function () {
    test()->actingAs(smsAdmin())
        ->post(route('admin.sms.settings.test'), ['test_number' => '0222110000'])
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('error', trans('messaging::app.sms.phone.not-mobile'));
});

it('refuses a platform test send when there are no credits', function () {
    makePlatformProfile();

    test()->actingAs(smsAdmin())
        ->post(route('admin.sms.settings.test'), ['test_number' => '0712345678'])
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('error');

    expect(Message::withoutTenancy()->where('tenant_id', smsTenant()->id)->count())->toBe(0);
});

it('queues a platform test send when credits are available', function () {
    makePlatformProfile();

    app(CreditLedger::class)->allocate(smsTenant(), 50);

    test()->actingAs(smsAdmin())
        ->post(route('admin.sms.settings.test'), ['test_number' => '0712345678'])
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('success');

    expect(Message::withoutTenancy()->where('tenant_id', smsTenant()->id)->count())->toBe(1);
});

it('does not serve the settings page to a guest', function () {
    test()->get(route('admin.sms.settings.index'))->assertRedirect();
});

/**
 * 9.5 — the provider setup must be asked for ONCE.
 *
 * Reported from use: every save demanded the API key and secret again.
 * They are encrypted and hidden, so the form could not show them back, and
 * `required` meant correcting a sender name cost you an API secret you may
 * never have kept a copy of.
 */
function ownProfileOf(): ?SenderProfile
{
    return SenderProfile::withoutTenancy()
        ->where('tenant_id', smsTenant()->id)
        ->orderByDesc('id')
        ->first();
}

function platformSenderName(): string
{
    // The log driver reports exactly this one name as registered, so it is
    // the only sender name that verifies in tests.
    return (string) config('messaging.platform.sender_name');
}

it('keeps the stored credentials when the key fields are left blank', function () {
    saveOwnProvider(platformSenderName(), 'live-key-123', 'live-secret-456');

    expect(ownProfileOf()->credentials['api_key'])->toBe('live-key-123');

    // Save again with the key fields omitted entirely -- what the form now
    // submits when the tenant only touched the sender name.
    test()->actingAs(smsAdmin())->post(route('admin.sms.settings.store'), [
        'driver'      => 'log',
        'sender_name' => platformSenderName(),
    ])->assertRedirect();

    $profile = ownProfileOf();

    expect($profile->credentials['api_key'])->toBe('live-key-123')
        ->and($profile->credentials['secret_key'])->toBe('live-secret-456')
        ->and($profile->status)->toBe(SenderProfileStatus::Approved);
});

it('still demands credentials the first time, when there is nothing stored', function () {
    SenderProfile::withoutTenancy()->where('tenant_id', smsTenant()->id)->delete();

    test()->actingAs(smsAdmin())
        ->post(route('admin.sms.settings.store'), [
            'driver'      => 'log',
            'sender_name' => platformSenderName(),
        ])
        ->assertSessionHasErrors(['api_key', 'secret_key']);
});

it('leaves a working provider alone when the new details fail verification', function () {
    saveOwnProvider(platformSenderName(), 'good-key', 'good-secret');

    $before = ownProfileOf();

    // A sender name the provider does not know. The old flow wrote the
    // profile as Pending BEFORE calling the provider, so this left a
    // working setup unable to send. A failed edit should cost the edit,
    // nothing else.
    test()->actingAs(smsAdmin())->post(route('admin.sms.settings.store'), [
        'driver'      => 'log',
        'sender_name' => 'NOTREGISTR',
    ])->assertRedirect();

    $after = ownProfileOf();

    expect($after->id)->toBe($before->id)
        ->and($after->sender_name)->toBe(platformSenderName())
        ->and($after->status)->toBe(SenderProfileStatus::Approved)
        ->and($after->credentials['api_key'])->toBe('good-key');
});

it('replaces the credentials when new ones are actually supplied', function () {
    saveOwnProvider(platformSenderName(), 'old-key', 'old-secret');

    saveOwnProvider(platformSenderName(), 'new-key', 'new-secret');

    expect(ownProfileOf()->credentials['api_key'])->toBe('new-key')
        ->and(ownProfileOf()->credentials['secret_key'])->toBe('new-secret');
});

it('tells the tenant the credentials are saved without showing them', function () {
    saveOwnProvider(platformSenderName(), 'secret-key-value', 'secret-secret-value');

    $response = test()->actingAs(smsAdmin())->get(route('admin.sms.settings.index'));

    $response->assertOK()
        ->assertDontSee('secret-key-value')
        ->assertDontSee('secret-secret-value')
        // A masked hint, so the state is visible without disclosure.
        ->assertSee('se••••••ue');
});
