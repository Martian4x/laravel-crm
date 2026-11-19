<?php

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Jobs\SendSmsJob;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\SmsSender;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Support\Facades\Queue;

/**
 * Phase 8.5 — the sending pipeline, end to end on the Log driver.
 *
 * The assertions that matter most are about money and consent: the amount
 * a user approves must be the amount charged, a refusal must leave no
 * charge behind, and an opt-out must hold however the number was typed.
 */
beforeEach(function () {
    $this->manager = app(TenantManager::class);
    $this->ledger = app(CreditLedger::class);
    $this->sender = app(SmsSender::class);

    $this->tenant = Tenant::firstOrCreate(['slug' => 'pipeline-test'], ['name' => 'Pipeline Test']);

    // The platform profile is the NULL-tenant row (8.2): a tenant in
    // platform mode owns no profile of their own, they send through this
    // one. Created outside tenant context so it is not stamped.
    $this->profile = SenderProfile::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'sender_name' => 'AZIMIOTESTPLAT'],
        ['label' => 'Platform', 'driver' => 'log', 'status' => 'approved'],
    );

    $this->manager->set($this->tenant);
});

afterEach(function () {
    $this->manager->forget();

    foreach ([Message::class, Batch::class, OptOut::class, SenderProfile::class, CreditLedgerEntry::class] as $model) {
        $model::withoutTenancy()->where('tenant_id', $this->tenant->id)->forceDelete();
    }

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOTESTPLAT')->forceDelete();

    $this->tenant->delete();
});

function tenantRow($test): Tenant
{
    return Tenant::withoutGlobalScopes()->find($test->tenant->id);
}

function runJob($test, Batch $batch, int $credits): void
{
    (new SendSmsJob($batch->id, $credits))->handle(app(DriverManager::class), $test->ledger);
}

it('prices a plan by segments times recipients', function () {
    $plan = $this->sender->plan(
        tenantRow($this),
        $this->profile,
        str_repeat('a', 161),
        ['0712345678', '0713345678', '0714345678'],
    );

    expect($plan->segments->segments)->toBe(2)
        ->and($plan->sendableCount())->toBe(3)
        ->and($plan->creditsRequired)->toBe(6);
});

it('drops duplicates that differ only in formatting', function () {
    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Hi', [
        '0712345678',
        '+255 712 345 678',
        '255-712-345-678',
        '0713345678',
    ]);

    expect($plan->sendableCount())->toBe(2)
        ->and($plan->excluded('duplicate'))->toHaveCount(2);
});

it('separates invalid numbers instead of silently dropping them', function () {
    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Hi', [
        '0712345678',
        '0222110000',
        'not a number',
    ]);

    expect($plan->sendableCount())->toBe(1)
        ->and($plan->excluded('invalid'))->toHaveCount(2)
        ->and($plan->excluded('invalid')[0]->rejection->value)->toBe('not_mobile');
});

it('excludes an opted-out number however it was typed', function () {
    OptOut::create(['dest_addr' => '+255712345678', 'source' => 'manual']);

    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Hi', ['0712 345 678']);

    expect($plan->sendableCount())->toBe(0)
        ->and($plan->excluded('opted_out'))->toHaveCount(1);
});

it('reports a shortfall rather than dispatching without credits', function () {
    $this->ledger->allocate($this->tenant, 1);

    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Hi', ['0712345678', '0713345678']);

    expect($plan->creditsRequired)->toBe(2)
        ->and($plan->hasSufficientCredits())->toBeFalse()
        ->and($plan->shortfall())->toBe(1)
        ->and($this->sender->dispatch(tenantRow($this), $this->profile, $plan))->toBeNull();
});

it('ignores platform credits entirely for an own-provider send', function () {
    $own = SenderProfile::create([
        'label'       => 'Mine',
        'sender_name' => 'MYBRAND',
        'driver'      => 'log',
        'status'      => 'approved',
    ]);

    $plan = $this->sender->plan(tenantRow($this), $own, 'Hi', ['0712345678']);

    expect($plan->mode)->toBe(SmsMode::Own)
        ->and($plan->creditsRequired)->toBe(0)
        ->and($plan->hasSufficientCredits())->toBeTrue();
});

it('writes one message row per recipient and reserves the credits', function () {
    Queue::fake();

    $this->ledger->allocate($this->tenant, 100);

    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Habari', ['0712345678', '0713345678']);
    $batch = $this->sender->dispatch(tenantRow($this), $this->profile, $plan);

    expect($batch)->not->toBeNull()
        ->and($batch->messages()->count())->toBe(2)
        ->and($batch->total_recipients)->toBe(2)
        ->and($batch->credits_estimated)->toBe(2)
        // Reserved, not yet charged: the cache is down but the ledger is not.
        ->and($this->ledger->balance(tenantRow($this)))->toBe(98)
        ->and($this->ledger->trueBalance(tenantRow($this)))->toBe(100);

    Queue::assertPushed(SendSmsJob::class);
});

it('settles credits only once the provider accepts', function () {
    $this->ledger->allocate($this->tenant, 100);

    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Habari', ['0712345678']);
    $batch = $this->sender->dispatch(tenantRow($this), $this->profile, $plan);

    runJob($this, $batch, 1);

    $message = $batch->messages()->first();

    expect($message->status)->toBe(SmsStatus::Submitted)
        ->and($message->provider_request_id)->not->toBeNull()
        ->and($message->credits_charged)->toBe(1)
        ->and($this->ledger->balance(tenantRow($this)))->toBe(99)
        // Only now does a real debit exist.
        ->and($this->ledger->trueBalance(tenantRow($this)))->toBe(99)
        ->and($batch->fresh()->status->value)->toBe('completed');
});

it('refuses to send from an unapproved sender name', function () {
    // A tenant's own sender name must be registered with the operator and
    // approved by a platform admin (8.17) before it can deliver anything.
    $pending = SenderProfile::create([
        'label'       => 'Awaiting approval',
        'sender_name' => 'NOTYETOK',
        'driver'      => 'log',
        'status'      => 'pending',
    ]);

    $plan = $this->sender->plan(tenantRow($this), $pending, 'Hi', ['0712345678']);
    $batch = $this->sender->dispatch(tenantRow($this), $pending, $plan);

    runJob($this, $batch, 0);

    expect($batch->messages()->first()->status)->toBe(SmsStatus::Failed)
        ->and($batch->messages()->first()->error_code)->toBe('sender_not_approved');
});

it('does not re-send messages that already reached a terminal state', function () {
    // Resumability: a retried job picks up where it stopped rather than
    // charging and delivering everything a second time.
    $this->ledger->allocate($this->tenant, 100);

    $plan = $this->sender->plan(tenantRow($this), $this->profile, 'Hi', ['0712345678', '0713345678']);
    $batch = $this->sender->dispatch(tenantRow($this), $this->profile, $plan);

    runJob($this, $batch, 2);

    $balanceAfterFirstRun = $this->ledger->balance(tenantRow($this));
    $requestIds = $batch->messages()->pluck('provider_request_id')->all();

    runJob($this, $batch, 2);

    expect($this->ledger->balance(tenantRow($this)))->toBe($balanceAfterFirstRun)
        ->and($batch->messages()->pluck('provider_request_id')->all())->toBe($requestIds);
});

it('holds a marketing batch until quiet hours end', function () {
    config()->set('messaging.compliance.quiet_hours.enabled', true);
    config()->set('messaging.compliance.quiet_hours.start', '00:00');
    config()->set('messaging.compliance.quiet_hours.end', '23:59');

    $plan = $this->sender->plan(
        tenantRow($this),
        $this->profile,
        'Offer',
        ['0712345678'],
        TemplateCategory::Marketing,
    );

    expect($plan->isDeferred())->toBeTrue()
        ->and($plan->deferredUntil)->not->toBeNull();
});

it('lets a transactional message through quiet hours', function () {
    config()->set('messaging.compliance.quiet_hours.enabled', true);
    config()->set('messaging.compliance.quiet_hours.start', '00:00');
    config()->set('messaging.compliance.quiet_hours.end', '23:59');

    $plan = $this->sender->plan(
        tenantRow($this),
        $this->profile,
        'Your quote is ready',
        ['0712345678'],
        TemplateCategory::Transactional,
    );

    expect($plan->isDeferred())->toBeFalse();
});
