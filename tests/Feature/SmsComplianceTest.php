<?php

use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\QuietHours;
use Azimio\Messaging\Services\SendPlanner;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Webkul\User\Models\User;

/**
 * Phase 8.12 — per-tenant quiet hours, daily cap and the unsubscribe
 * notice.
 *
 * The distinction these tests protect: a NULL tenant column means "use the
 * platform default", which is NOT the same as 0. Collapsing the two would
 * silently turn a tenant's sending off, or uncap a tenant who never asked.
 */
function compAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function compTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function compProfile(): SenderProfile
{
    return SenderProfile::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'sender_name' => 'AZIMIOCOMP'],
        ['label' => 'Platform', 'driver' => 'log', 'status' => 'approved'],
    );
}

beforeEach(function () {
    config()->set('messaging.compliance.quiet_hours.enabled', true);
    config()->set('messaging.compliance.quiet_hours.start', '21:00');
    config()->set('messaging.compliance.quiet_hours.end', '08:00');
    config()->set('messaging.compliance.daily_send_cap', 5000);
});

afterEach(function () {
    $tenant = compTenant();

    foreach ([Message::class, Batch::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
    }

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOCOMP')->forceDelete();

    Tenant::withoutGlobalScopes()->whereKey($tenant->id)->update([
        'sms_credits'             => 0,
        'sms_quiet_hours_enabled' => null,
        'sms_quiet_start'         => null,
        'sms_quiet_end'           => null,
        'sms_daily_cap'           => null,
    ]);
});

it('uses the platform default when a tenant has set nothing', function () {
    $quiet = app(QuietHours::class);

    expect($quiet->start(compTenant()))->toBe('21:00')
        ->and($quiet->end(compTenant()))->toBe('08:00')
        ->and($quiet->dailyCap(compTenant()))->toBe(5000);
});

it('honours a tenant override over the platform default', function () {
    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update([
        'sms_quiet_start' => '22:30',
        'sms_quiet_end'   => '06:00',
        'sms_daily_cap'   => 100,
    ]);

    $quiet = app(QuietHours::class);

    expect($quiet->start(compTenant()))->toBe('22:30')
        ->and($quiet->end(compTenant()))->toBe('06:00')
        ->and($quiet->dailyCap(compTenant()))->toBe(100);
});

it('treats a zero cap as uncapped, not as no sending', function () {
    // The distinction a NULL column exists to preserve.
    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update(['sms_daily_cap' => 0]);

    expect(app(QuietHours::class)->dailyCap(compTenant()))->toBeNull();
});

it('computes the quiet window across midnight', function () {
    $quiet = app(QuietHours::class);
    $tz = $quiet->timezone();

    expect($quiet->isQuiet(CarbonImmutable::parse('2026-11-19 23:30', $tz), compTenant()))->toBeTrue()
        ->and($quiet->isQuiet(CarbonImmutable::parse('2026-11-19 03:00', $tz), compTenant()))->toBeTrue()
        ->and($quiet->isQuiet(CarbonImmutable::parse('2026-11-19 12:00', $tz), compTenant()))->toBeFalse()
        // 08:00 is the opening, so not quiet.
        ->and($quiet->isQuiet(CarbonImmutable::parse('2026-11-19 08:00', $tz), compTenant()))->toBeFalse();
});

it('lets a tenant disable quiet hours entirely', function () {
    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)
        ->update(['sms_quiet_hours_enabled' => 0]);

    $quiet = app(QuietHours::class);

    expect($quiet->enabled(compTenant()))->toBeFalse()
        ->and($quiet->permits(
            TemplateCategory::Marketing,
            CarbonImmutable::parse('2026-11-19 23:30', $quiet->timezone()),
            compTenant(),
        ))->toBeTrue();
});

it('appends the unsubscribe notice to marketing only', function () {
    $planner = app(SendPlanner::class);

    $marketing = $planner->applyOptOutNotice('Big sale today', TemplateCategory::Marketing);
    $transactional = $planner->applyOptOutNotice('Your quote is ready', TemplateCategory::Transactional);

    expect($marketing)->toContain(trans('messaging::app.sms.opt-out-notice'))
        // A quote with "reply STOP" on it reads as spam and is not what the
        // rule is for.
        ->and($transactional)->toBe('Your quote is ready');
});

it('does not stack the notice when it is already present', function () {
    $planner = app(SendPlanner::class);

    $once = $planner->applyOptOutNotice('Sale', TemplateCategory::Marketing);
    $twice = $planner->applyOptOutNotice($once, TemplateCategory::Marketing);

    expect($twice)->toBe($once);
});

it('counts the notice in the quoted price', function () {
    compProfile();
    app(CreditLedger::class)->allocate(compTenant(), 100);

    // 150 characters plus the notice crosses one segment, so the notice has
    // to be in the body BEFORE counting or the price understates the cost.
    $body = str_repeat('a', 150);

    $plan = app(SmsSender::class)->plan(
        compTenant(),
        compProfile(),
        $body,
        ['0712345678'],
        TemplateCategory::Marketing,
    );

    expect($plan->body)->toContain(trans('messaging::app.sms.opt-out-notice'))
        ->and($plan->segments->segments)->toBe(2)
        ->and($plan->creditsRequired)->toBe(2);
});

it('sends the body that was priced, notice included', function () {
    compProfile();
    app(CreditLedger::class)->allocate(compTenant(), 100);

    $sender = app(SmsSender::class);

    $plan = $sender->plan(
        compTenant(),
        compProfile(),
        'Bei nzuri leo',
        ['0712345678'],
        TemplateCategory::Marketing,
    );

    $batch = $sender->dispatch(compTenant(), compProfile(), $plan);

    expect($batch->body)->toBe($plan->body)
        ->and($batch->body)->toContain(trans('messaging::app.sms.opt-out-notice'));
});

it('reports how much of the daily cap is left', function () {
    compProfile();
    app(CreditLedger::class)->allocate(compTenant(), 100);

    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update(['sms_daily_cap' => 3]);

    $plan = app(SmsSender::class)->plan(
        compTenant(),
        compProfile(),
        'Habari',
        ['0712345678', '0713345678'],
        TemplateCategory::Transactional,
    );

    expect($plan->dailyCapRemaining)->toBe(3)
        ->and($plan->exceedsDailyCap())->toBeFalse()
        ->and($plan->isSendable())->toBeTrue();
});

it('blocks a send that would exceed the daily cap', function () {
    compProfile();
    app(CreditLedger::class)->allocate(compTenant(), 100);

    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update(['sms_daily_cap' => 2]);

    $sender = app(SmsSender::class);

    $plan = $sender->plan(
        compTenant(),
        compProfile(),
        'Habari',
        ['0712345678', '0713345678', '0714345678'],
        TemplateCategory::Transactional,
    );

    expect($plan->exceedsDailyCap())->toBeTrue()
        ->and($plan->isSendable())->toBeFalse()
        // dispatch() refuses on isSendable(), so a new guard on the plan
        // cannot be forgotten at the call site.
        ->and($sender->dispatch(compTenant(), compProfile(), $plan))->toBeNull();

    expect(Message::withoutTenancy()->where('tenant_id', compTenant()->id)->count())->toBe(0);
});

it('counts messages already sent today against the cap', function () {
    compProfile();
    app(CreditLedger::class)->allocate(compTenant(), 100);

    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update(['sms_daily_cap' => 3]);

    $sender = app(SmsSender::class);

    $first = $sender->plan(compTenant(), compProfile(), 'Habari', ['0712345678']);
    $sender->dispatch(compTenant(), compProfile(), $first);

    $second = $sender->plan(compTenant(), compProfile(), 'Habari', ['0713345678', '0714345678', '0715345678']);

    expect($second->dailyCapRemaining)->toBe(2)
        ->and($second->exceedsDailyCap())->toBeTrue();
});

it('saves per-tenant sending rules from the settings page', function () {
    test()->actingAs(compAdmin())
        ->post(route('admin.sms.settings.compliance'), [
            'quiet_hours_enabled' => '1',
            'quiet_start'         => '22:00',
            'quiet_end'           => '07:00',
            'daily_cap'           => '250',
        ])
        ->assertRedirect(route('admin.sms.settings.index'))
        ->assertSessionHas('success');

    $tenant = compTenant()->fresh();

    expect($tenant->sms_quiet_start)->toBe('22:00')
        ->and($tenant->sms_quiet_end)->toBe('07:00')
        ->and((int) $tenant->sms_daily_cap)->toBe(250)
        ->and((bool) $tenant->sms_quiet_hours_enabled)->toBeTrue();
});

it('stores a blank field as null so the platform default applies', function () {
    Tenant::withoutGlobalScopes()->whereKey(compTenant()->id)->update(['sms_daily_cap' => 99]);

    test()->actingAs(compAdmin())
        ->post(route('admin.sms.settings.compliance'), ['quiet_hours_enabled' => '1'])
        ->assertSessionHas('success');

    $tenant = compTenant()->fresh();

    expect($tenant->sms_daily_cap)->toBeNull()
        ->and(app(QuietHours::class)->dailyCap($tenant))->toBe(5000);
});

it('shows the sending rules on the settings page', function () {
    test()->actingAs(compAdmin())
        ->get(route('admin.sms.settings.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.compliance.title'))
        ->assertSee(trans('messaging::app.sms.compliance.tcra-note'));
});
