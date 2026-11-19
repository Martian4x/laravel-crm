<?php

use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Mail\SmsFailureRateAlert;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\User;

/**
 * Phase 8.18 — kill switch, failure-rate alerting and monthly usage.
 *
 * The kill switch must stop platform sending and leave own-provider tenants
 * alone: it is their credit and their provider account, not ours to switch
 * off.
 */
function ksSuper(): User
{
    return User::withoutTenancy()->where('is_super_admin', 1)->firstOrFail();
}

function ksTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function ksPlatformProfile(): SenderProfile
{
    $existing = SenderProfile::platform();

    if ($existing) {
        return $existing;
    }

    $profile = new SenderProfile;

    $profile->forceFill([
        'tenant_id'   => null,
        'label'       => 'Platform',
        'sender_name' => 'AZIMIOKS',
        'driver'      => 'log',
        'status'      => SenderProfileStatus::Approved->value,
    ])->save();

    return $profile;
}

function ksOwnProfile(): SenderProfile
{
    $profile = new SenderProfile;

    $profile->forceFill([
        'tenant_id'   => ksTenant()->id,
        'label'       => 'Own',
        'sender_name' => 'KSOWN',
        'driver'      => 'log',
        'status'      => SenderProfileStatus::Approved->value,
    ])->save();

    return $profile;
}

function ksMessages(SenderProfile $profile, int $failed, int $delivered): void
{
    $i = 0;

    foreach ([SmsStatus::Failed->value => $failed, SmsStatus::Delivered->value => $delivered] as $status => $count) {
        for ($n = 0; $n < $count; $n++) {
            $i++;

            $message = new Message;

            $message->forceFill([
                'tenant_id'         => ksTenant()->id,
                'sender_profile_id' => $profile->id,
                'dest_addr'         => '+2557120'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'body'              => 'Habari',
                'segments'          => 1,
                'direction'         => 'outbound',
                'status'            => $status,
            ])->save();
        }
    }
}

afterEach(function () {
    foreach ([Message::class, Batch::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', ksTenant()->id)->forceDelete();
    }

    SenderProfile::withoutTenancy()->whereIn('sender_name', ['AZIMIOKS', 'KSOWN'])->forceDelete();
    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0]);
    Cache::flush();
});

it('pauses and resumes platform sending from the super panel', function () {
    ksPlatformProfile();

    test()->actingAs(ksSuper())
        ->post(route('super.sms.toggle-pause'))
        ->assertRedirect(route('super.sms.index'))
        ->assertSessionHas('success');

    expect(SenderProfile::platform()->status)->toBe(SenderProfileStatus::Disabled);

    test()->actingAs(ksSuper())->post(route('super.sms.toggle-pause'));

    expect(SenderProfile::platform()->status)->toBe(SenderProfileStatus::Approved);
});

it('blocks a platform send while paused', function () {
    $profile = ksPlatformProfile();
    app(CreditLedger::class)->allocate(ksTenant(), 100);

    $profile->forceFill(['status' => SenderProfileStatus::Disabled->value])->save();

    $sender = app(SmsSender::class);
    $plan = $sender->plan(ksTenant(), $profile, 'Habari', ['0712345678']);

    expect($plan->isPlatformPaused())->toBeTrue()
        ->and($plan->isSendable())->toBeFalse()
        ->and($sender->dispatch(ksTenant(), $profile, $plan))->toBeNull();

    expect(Message::withoutTenancy()->where('tenant_id', ksTenant()->id)->count())->toBe(0);
});

it('leaves own-provider tenants sending while platform is paused', function () {
    // Their credit, their account. The switch is ours to throw, not theirs
    // to suffer.
    ksPlatformProfile()->forceFill(['status' => SenderProfileStatus::Disabled->value])->save();

    $own = ksOwnProfile();
    $sender = app(SmsSender::class);

    $plan = $sender->plan(ksTenant(), $own, 'Habari', ['0712345678']);

    expect($plan->isPlatformPaused())->toBeFalse()
        ->and($plan->isSendable())->toBeTrue()
        ->and($sender->dispatch(ksTenant(), $own, $plan))->not->toBeNull();
});

it('hides the kill switch from a tenant user', function () {
    ksPlatformProfile();

    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();

    test()->actingAs($tenantAdmin)
        ->post(route('super.sms.toggle-pause'))
        ->assertNotFound();

    expect(SenderProfile::platform()->status)->toBe(SenderProfileStatus::Approved);
});

it('shows the pause state and monthly usage on the super page', function () {
    ksPlatformProfile();

    test()->actingAs(ksSuper())
        ->get(route('super.sms.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.super.active-title'))
        ->assertSee(trans('messaging::app.super.usage-title'));
});

it('alerts platform admins when a tenant failure rate is high', function () {
    Mail::fake();

    // 10 of 20 failed: 50%, over the 25% threshold.
    ksMessages(ksPlatformProfile(), failed: 10, delivered: 10);

    test()->artisan('azimio:sms:check-failure-rate')->assertSuccessful();

    Mail::assertQueued(SmsFailureRateAlert::class);
});

it('ignores a failure rate below the minimum volume', function () {
    // One failure out of two is 50% and means nothing; alerting on it would
    // make the alert worthless.
    Mail::fake();

    ksMessages(ksPlatformProfile(), failed: 1, delivered: 1);

    test()->artisan('azimio:sms:check-failure-rate')->assertSuccessful();

    Mail::assertNotQueued(SmsFailureRateAlert::class);
});

it('alerts only once a day per tenant', function () {
    Mail::fake();

    ksMessages(ksPlatformProfile(), failed: 25, delivered: 0);

    test()->artisan('azimio:sms:check-failure-rate')->assertSuccessful();
    test()->artisan('azimio:sms:check-failure-rate')->assertSuccessful();

    // A provider outage would otherwise mail on every hourly run.
    Mail::assertQueued(SmsFailureRateAlert::class, 1);
});
