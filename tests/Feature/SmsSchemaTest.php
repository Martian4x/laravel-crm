<?php

use Azimio\Messaging\Enums\Encoding;
use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\Template;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8.2 — schema and model layer for the SMS module.
 *
 * Covers the tenancy guarantees that 8.22 requires and the two decisions
 * most expensive to get wrong later: credential encryption (8.0.5) and the
 * platform sender profile never being readable by a tenant.
 */
beforeEach(function () {
    $this->manager = app(TenantManager::class);

    $this->tenantA = Tenant::firstOrCreate(['slug' => 'sms-test-a'], ['name' => 'SMS Test A']);
    $this->tenantB = Tenant::firstOrCreate(['slug' => 'sms-test-b'], ['name' => 'SMS Test B']);
});

afterEach(function () {
    $this->manager->forget();

    foreach ([SenderProfile::class, Message::class, OptOut::class, Template::class] as $model) {
        $model::withoutTenancy()
            ->whereIn('tenant_id', [$this->tenantA->id, $this->tenantB->id])
            ->forceDelete();
    }

    SenderProfile::withoutTenancy()->whereNull('tenant_id')
        ->where('sender_name', 'AZIMIOTEST')->forceDelete();

    $this->tenantA->delete();
    $this->tenantB->delete();
});

it('stamps a sender profile with the active tenant', function () {
    $this->manager->set($this->tenantA);

    $profile = SenderProfile::create([
        'label'       => 'Mine',
        'sender_name' => 'TENANTA',
        'driver'      => 'log',
    ]);

    expect($profile->tenant_id)->toBe($this->tenantA->id)
        ->and($profile->isPlatform())->toBeFalse();
});

it('encrypts sender profile credentials at rest', function () {
    $this->manager->set($this->tenantA);

    $profile = SenderProfile::create([
        'label'       => 'Mine',
        'sender_name' => 'TENANTA',
        'driver'      => 'beem',
        'credentials' => ['api_key' => 'plaintext-key', 'secret_key' => 'plaintext-secret'],
    ]);

    $raw = DB::table('sms_sender_profiles')->where('id', $profile->id)->value('credentials');

    // The stored bytes must not contain the secret in readable form.
    expect($raw)->not->toContain('plaintext-key')
        ->and($raw)->not->toContain('plaintext-secret');

    // But the model must round-trip it.
    expect($profile->fresh()->credentials['api_key'])->toBe('plaintext-key');
});

it('hides the platform sender profile from tenants', function () {
    $platform = SenderProfile::withoutTenancy()->create([
        'tenant_id'   => null,
        'label'       => 'Platform',
        'sender_name' => 'AZIMIOTEST',
        'driver'      => 'log',
        'credentials' => ['api_key' => 'platform-secret'],
    ]);

    $this->manager->set($this->tenantA);

    // A tenant must not reach the platform profile or its credentials.
    expect(SenderProfile::find($platform->id))->toBeNull()
        ->and(SenderProfile::count())->toBe(0);
});

it('keeps one tenant out of another tenant SMS log', function () {
    $this->manager->set($this->tenantA);

    $message = Message::create([
        'dest_addr' => '+255712000001',
        'body'      => 'Habari',
        'segments'  => 1,
    ]);

    $this->manager->set($this->tenantB);

    expect(Message::find($message->id))->toBeNull()
        ->and(Message::count())->toBe(0);
});

it('scopes opt-outs per tenant so one company cannot mute another', function () {
    $this->manager->set($this->tenantA);

    OptOut::create(['dest_addr' => '+255712000002', 'source' => 'manual']);

    expect(OptOut::blocks('+255712000002'))->toBeTrue();

    $this->manager->set($this->tenantB);

    expect(OptOut::blocks('+255712000002'))->toBeFalse();
});

it('shows platform templates to every tenant but keeps them read-only', function () {
    $platform = Template::withoutTenancy()->create([
        'tenant_id' => null,
        'name'      => 'Platform Welcome',
        'body'      => 'Karibu {{person.first_name}}',
        'category'  => 'transactional',
    ]);

    $this->manager->set($this->tenantA);

    $seen = Template::find($platform->id);

    expect($seen)->not->toBeNull()
        ->and($seen->isPlatformDefault())->toBeTrue();

    // BelongsToTenant blocks tenants editing a platform default.
    $seen->name = 'Hijacked';

    expect($seen->save())->toBeFalse()
        ->and($platform->fresh()->name)->toBe('Platform Welcome');

    Template::withoutTenancy()->whereKey($platform->id)->forceDelete();
});

it('casts message status, encoding and cost source to enums', function () {
    $this->manager->set($this->tenantA);

    $message = Message::create([
        'dest_addr'   => '+255712000003',
        'body'        => 'Test',
        'status'      => SmsStatus::FailedOwnProvider->value,
        'encoding'    => Encoding::Gsm7->value,
        'cost_source' => SmsMode::Own->value,
    ]);

    $fresh = $message->fresh();

    expect($fresh->status)->toBe(SmsStatus::FailedOwnProvider)
        ->and($fresh->encoding)->toBe(Encoding::Gsm7)
        ->and($fresh->cost_source)->toBe(SmsMode::Own)
        ->and($fresh->cost_source->usesPlatformCredits())->toBeFalse();
});

it('offers a platform retry only when there is no credit shortfall', function () {
    $this->manager->set($this->tenantA);

    $covered = Message::create([
        'dest_addr' => '+255712000004',
        'body'      => 'Test',
        'status'    => SmsStatus::FailedOwnProvider->value,
    ]);

    $short = Message::create([
        'dest_addr'        => '+255712000005',
        'body'             => 'Test',
        'status'           => SmsStatus::FailedOwnProvider->value,
        'credit_shortfall' => 12,
    ]);

    expect($covered->canOfferPlatformRetry())->toBeTrue()
        ->and($short->canOfferPlatformRetry())->toBeFalse();
});
