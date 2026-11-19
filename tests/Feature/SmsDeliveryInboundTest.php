<?php

use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Mail\LowSmsBalance;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\WebhookEvent;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 8.19 — delivery-report polling and inbound SMS, plus the
 * low-balance notifier that completes 8.13.
 *
 * Delivery reports are POLLED, not pushed: Beem has no callback for them.
 * Inbound IS a callback but carries no signature Beem will define, so these
 * tests pin that every payload is logged before it is trusted and that a
 * rejected one is refused.
 */
function dlrTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function dlrProfile(?int $tenantId = null, string $name = 'AZIMIODLR'): SenderProfile
{
    $existing = SenderProfile::withoutTenancy()->where('sender_name', $name)->first();

    if ($existing) {
        return $existing;
    }

    $profile = new SenderProfile;

    // tenant_id is deliberately not mass-assignable (8.2), so firstOrCreate
    // would drop it and silently produce a PLATFORM profile instead.
    $profile->forceFill([
        'tenant_id'   => $tenantId,
        'label'       => 'Test',
        'sender_name' => $name,
        'driver'      => 'log',
        'status'      => 'approved',
    ])->save();

    return $profile;
}

/**
 * The seeder ships more than one tenant and they all start on zero credits,
 * so a low-balance test has to control every tenant, not just its own.
 */
function allTenantsHealthy(): void
{
    Tenant::withoutGlobalScopes()->update(['sms_credits' => 9999]);
}

function submittedMessage(SenderProfile $profile, string $number, string $requestId = 'req-1'): Message
{
    $message = new Message;

    $message->forceFill([
        'tenant_id'           => dlrTenant()->id,
        'sender_profile_id'   => $profile->id,
        'dest_addr'           => $number,
        'body'                => 'Habari',
        'segments'            => 1,
        'status'              => SmsStatus::Submitted->value,
        'provider_request_id' => $requestId,
        'submitted_at'        => now()->subHour(),
    ])->save();

    return $message;
}

afterEach(function () {
    foreach ([Message::class, Batch::class, OptOut::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', dlrTenant()->id)->forceDelete();
    }

    SenderProfile::withoutTenancy()->whereIn('sender_name', ['AZIMIODLR', '255700000111'])->forceDelete();
    WebhookEvent::query()->forceDelete();

    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0, 'sms_mode' => 'platform']);
    Cache::flush();
});

it('polls and resolves a submitted message to delivered', function () {
    // The Log driver reports everything delivered.
    $message = submittedMessage(dlrProfile(), '+255712345678');

    test()->artisan('azimio:sms:poll-delivery-reports')->assertSuccessful();

    expect($message->fresh()->status)->toBe(SmsStatus::Delivered)
        ->and($message->fresh()->delivered_at)->not->toBeNull();
});

it('leaves a message alone until the provider minimum age has passed', function () {
    // Beem asks for at least five minutes after submitting.
    $message = submittedMessage(dlrProfile(), '+255712345678');
    $message->forceFill(['submitted_at' => now()])->save();

    test()->artisan('azimio:sms:poll-delivery-reports')->assertSuccessful();

    expect($message->fresh()->status)->toBe(SmsStatus::Submitted);
});

it('gives up on messages older than the polling window', function () {
    // Polling these forever costs one request each and will never resolve.
    $message = submittedMessage(dlrProfile(), '+255712345678');
    $message->forceFill(['submitted_at' => now()->subDays(5)])->save();

    test()->artisan('azimio:sms:poll-delivery-reports')->assertSuccessful();

    expect($message->fresh()->status)->toBe(SmsStatus::Submitted);
});

it('does not poll when polling is disabled', function () {
    config()->set('messaging.delivery_reports.enabled', false);

    $message = submittedMessage(dlrProfile(), '+255712345678');

    test()->artisan('azimio:sms:poll-delivery-reports')->assertSuccessful();

    expect($message->fresh()->status)->toBe(SmsStatus::Submitted);
});

it('refreshes the batch counters after a status change', function () {
    $profile = dlrProfile();

    $batch = new Batch;
    $batch->forceFill([
        'tenant_id' => dlrTenant()->id, 'sender_profile_id' => $profile->id,
        'name' => 'DLR batch', 'body' => 'Habari', 'total_recipients' => 1,
        'submitted_count' => 1, 'status' => 'completed',
    ])->save();

    $message = submittedMessage($profile, '+255712345678');
    $message->forceFill(['batch_id' => $batch->id])->save();

    test()->artisan('azimio:sms:poll-delivery-reports')->assertSuccessful();

    expect($batch->fresh()->delivered_count)->toBe(1)
        ->and($batch->fresh()->submitted_count)->toBe(0);
});

it('logs an inbound callback before trusting it', function () {
    config()->set('messaging.webhooks.shared_secret', null);

    // The receiving number is held as the tenant profile's sender_name.
    dlrProfile(dlrTenant()->id, '255700000111');

    test()->postJson(route('messaging.webhook.inbound'), [
        'transaction_id' => 'tx-1',
        'from'           => '255712345678',
        'to'             => '255700000111',
        'message'        => ['text' => 'Nataka maelezo'],
    ])->assertOK()->assertJson(['transaction_id' => 'tx-1', 'successful' => true]);

    $event = WebhookEvent::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->signature_valid)->toBeTrue()
        ->and($event->processed_at)->not->toBeNull();

    $message = Message::withoutTenancy()->where('direction', 'inbound')->first();

    expect($message)->not->toBeNull()
        ->and($message->dest_addr)->toBe('+255712345678')
        ->and($message->body)->toBe('Nataka maelezo')
        ->and($message->tenant_id)->toBe(dlrTenant()->id)
        ->and($message->credits_charged)->toBe(0);
});

it('refuses a callback with a bad shared secret but still logs it', function () {
    // A run of these is what a forged-callback attempt looks like, so the
    // rejected payload is kept rather than discarded.
    config()->set('messaging.webhooks.shared_secret', 'right-secret');

    dlrProfile(dlrTenant()->id, '255700000111');

    test()->postJson(route('messaging.webhook.inbound'), [
        'transaction_id' => 'tx-bad',
        'from'           => '255712345678',
        'to'             => '255700000111',
        'message'        => ['text' => 'hello'],
    ], ['X-Azimio-Secret' => 'wrong-secret'])->assertStatus(403);

    $event = WebhookEvent::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->signature_valid)->toBeFalse()
        ->and($event->processed_at)->toBeNull()
        ->and(Message::withoutTenancy()->where('direction', 'inbound')->count())->toBe(0);
});

it('opts a number out when it replies STOP', function () {
    config()->set('messaging.webhooks.shared_secret', null);

    dlrProfile(dlrTenant()->id, '255700000111');

    test()->postJson(route('messaging.webhook.inbound'), [
        'transaction_id' => 'tx-stop',
        'from'           => '255712345678',
        'to'             => '255700000111',
        'message'        => ['text' => 'STOP'],
    ])->assertOK();

    expect(OptOut::withoutTenancy()->where('tenant_id', dlrTenant()->id)->first())
        ->not->toBeNull()
        ->and(OptOut::withoutTenancy()->where('tenant_id', dlrTenant()->id)->value('dest_addr'))
        ->toBe('+255712345678')
        ->and(OptOut::withoutTenancy()->where('tenant_id', dlrTenant()->id)->value('source'))
        ->toBe('stop_reply');
});

it('honours ACHA as a Swahili opt-out keyword', function () {
    config()->set('messaging.webhooks.shared_secret', null);

    dlrProfile(dlrTenant()->id, '255700000111');

    test()->postJson(route('messaging.webhook.inbound'), [
        'transaction_id' => 'tx-acha',
        'from'           => '255712345678',
        'to'             => '255700000111',
        'message'        => ['text' => ' acha '],
    ])->assertOK();

    expect(OptOut::withoutTenancy()->where('tenant_id', dlrTenant()->id)->count())->toBe(1);
});

it('processes a retried callback only once', function () {
    // Beem retries, so a repeat must not log the message twice.
    config()->set('messaging.webhooks.shared_secret', null);

    dlrProfile(dlrTenant()->id, '255700000111');

    $payload = [
        'transaction_id' => 'tx-dup',
        'from'           => '255712345678',
        'to'             => '255700000111',
        'message'        => ['text' => 'Habari'],
    ];

    test()->postJson(route('messaging.webhook.inbound'), $payload)->assertOK();
    test()->postJson(route('messaging.webhook.inbound'), $payload)->assertOK();

    expect(Message::withoutTenancy()->where('direction', 'inbound')->count())->toBe(1);
});

it('ignores a callback for a number no tenant owns', function () {
    // Tenant context comes from the profile that owns the receiving number,
    // never from the request body.
    config()->set('messaging.webhooks.shared_secret', null);

    test()->postJson(route('messaging.webhook.inbound'), [
        'transaction_id' => 'tx-unknown',
        'from'           => '255712345678',
        'to'             => '255799999999',
        'message'        => ['text' => 'Habari'],
    ])->assertOK();

    expect(Message::withoutTenancy()->where('direction', 'inbound')->count())->toBe(0);
});

it('emails a tenant whose credits have run low', function () {
    Mail::fake();

    allTenantsHealthy();
    Tenant::withoutGlobalScopes()->whereKey(dlrTenant()->id)->update(['sms_credits' => 5]);

    test()->artisan('azimio:sms:notify-low-balance')->assertSuccessful();

    Mail::assertQueued(LowSmsBalance::class);
});

it('does not email a tenant with a healthy balance', function () {
    Mail::fake();

    allTenantsHealthy();

    test()->artisan('azimio:sms:notify-low-balance')->assertSuccessful();

    Mail::assertNotQueued(LowSmsBalance::class);
});

it('warns only once a week so the alert stays meaningful', function () {
    Mail::fake();

    allTenantsHealthy();
    Tenant::withoutGlobalScopes()->whereKey(dlrTenant()->id)->update(['sms_credits' => 5]);

    test()->artisan('azimio:sms:notify-low-balance')->assertSuccessful();
    test()->artisan('azimio:sms:notify-low-balance')->assertSuccessful();

    Mail::assertQueued(LowSmsBalance::class, 1);
});

it('ignores own-provider tenants, whose balance is not ours to watch', function () {
    Mail::fake();

    allTenantsHealthy();

    Tenant::withoutGlobalScopes()->whereKey(dlrTenant()->id)
        ->update(['sms_mode' => 'own', 'sms_credits' => 5]);

    test()->artisan('azimio:sms:notify-low-balance')->assertSuccessful();

    Mail::assertNotQueued(LowSmsBalance::class);
});
