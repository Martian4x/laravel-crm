<?php

use Azimio\Messaging\Enums\CreditType;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Support\Reservation;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;

/**
 * Phase 8.5 — the credit ledger.
 *
 * This is the money. The ledger is append-only truth and
 * tenants.sms_credits is a cache of it, so the tests below care about two
 * things above all: a reservation can never be double-spent, and a
 * pre-submission refusal never leaves a charge behind (decision 8.0.2).
 */
beforeEach(function () {
    $this->ledger = app(CreditLedger::class);
    $this->manager = app(TenantManager::class);

    $this->tenant = Tenant::firstOrCreate(['slug' => 'ledger-test'], ['name' => 'Ledger Test']);
    $this->manager->set($this->tenant);
});

afterEach(function () {
    $this->manager->forget();

    CreditLedgerEntry::withoutTenancy()->where('tenant_id', $this->tenant->id)->forceDelete();

    $this->tenant->delete();
});

function freshTenant($test): Tenant
{
    return Tenant::withoutGlobalScopes()->find($test->tenant->id);
}

it('allocates credits and records them in the ledger', function () {
    $entry = $this->ledger->allocate($this->tenant, 100, null, 'Opening balance');

    expect($this->ledger->balance(freshTenant($this)))->toBe(100)
        ->and($entry->type)->toBe(CreditType::Allocation)
        ->and($entry->credits)->toBe(100)
        ->and($entry->balance_after)->toBe(100);
});

it('reserves credits by removing them from the available balance', function () {
    $this->ledger->allocate($this->tenant, 100);

    $reservation = $this->ledger->reserve(freshTenant($this), 30);

    expect($reservation)->not->toBeNull()
        ->and($reservation->credits)->toBe(30)
        // Held, so no longer spendable...
        ->and($this->ledger->balance(freshTenant($this)))->toBe(70)
        // ...but not yet charged: the ledger still shows the allocation only.
        ->and($this->ledger->trueBalance(freshTenant($this)))->toBe(100);
});

it('refuses a reservation the balance cannot cover', function () {
    $this->ledger->allocate($this->tenant, 10);

    expect($this->ledger->reserve(freshTenant($this), 11))->toBeNull()
        // A refused reservation must not nibble the balance.
        ->and($this->ledger->balance(freshTenant($this)))->toBe(10);
});

it('never lets two concurrent sends spend the same last credits', function () {
    // The double-spend case from 8.25. Both reservations ask for the whole
    // balance; the conditional UPDATE means exactly one can win.
    $this->ledger->allocate($this->tenant, 50);

    $first = $this->ledger->reserve(freshTenant($this), 50);
    $second = $this->ledger->reserve(freshTenant($this), 50);

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and($this->ledger->balance(freshTenant($this)))->toBe(0);
});

it('settles a reservation into a permanent debit', function () {
    $this->ledger->allocate($this->tenant, 100);

    $reservation = $this->ledger->reserve(freshTenant($this), 40);
    $this->ledger->settle($reservation, 'Batch #1');

    expect($this->ledger->balance(freshTenant($this)))->toBe(60)
        // Cache and ledger agree once settled.
        ->and($this->ledger->trueBalance(freshTenant($this)))->toBe(60);

    $debit = CreditLedgerEntry::where('type', CreditType::Debit->value)->latest('id')->first();

    expect($debit->credits)->toBe(-40)
        ->and($debit->balance_after)->toBe(60);
});

it('releases a refused send without writing any ledger row', function () {
    // Decision 8.0.2: a pre-submission refusal was never charged, so a
    // debit-and-refund pair would be audit noise rather than information.
    $this->ledger->allocate($this->tenant, 100);

    $before = CreditLedgerEntry::count();

    $reservation = $this->ledger->reserve(freshTenant($this), 25);
    $this->ledger->release($reservation);

    expect($this->ledger->balance(freshTenant($this)))->toBe(100)
        ->and($this->ledger->trueBalance(freshTenant($this)))->toBe(100)
        ->and(CreditLedgerEntry::count())->toBe($before);
});

it('treats a zero-credit reservation as a no-op', function () {
    // Own-provider sends reserve nothing, and must not be special-cased by
    // every caller.
    $this->ledger->allocate($this->tenant, 10);

    $reservation = $this->ledger->reserve(freshTenant($this), 0);

    expect($reservation)->not->toBeNull()
        ->and($reservation->isEmpty())->toBeTrue()
        ->and($this->ledger->settle($reservation))->toBeNull()
        ->and($this->ledger->balance(freshTenant($this)))->toBe(10);
});

it('reconciles a cache that drifted below the ledger', function () {
    // What an abandoned reservation looks like: credits held, no ledger
    // row. Reconciling must return them to the tenant.
    $this->ledger->allocate($this->tenant, 100);
    $this->ledger->reserve(freshTenant($this), 30);

    expect($this->ledger->balance(freshTenant($this)))->toBe(70);

    $drift = $this->ledger->reconcile(freshTenant($this));

    expect($drift)->toBe(30)
        ->and($this->ledger->balance(freshTenant($this)))->toBe(100);
});

it('reports no drift when the cache is correct', function () {
    $this->ledger->allocate($this->tenant, 100);
    $this->ledger->settle($this->ledger->reserve(freshTenant($this), 40));

    expect($this->ledger->reconcile(freshTenant($this)))->toBe(0);
});

it('keeps a running balance across many movements', function () {
    $this->ledger->allocate($this->tenant, 100);
    $this->ledger->settle($this->ledger->reserve(freshTenant($this), 10));
    $this->ledger->allocate($this->tenant, 25, null, 'Top up');
    $this->ledger->settle($this->ledger->reserve(freshTenant($this), 5));

    expect($this->ledger->balance(freshTenant($this)))->toBe(110)
        ->and($this->ledger->trueBalance(freshTenant($this)))->toBe(110);
});

it('scopes credits to one tenant', function () {
    $other = Tenant::firstOrCreate(['slug' => 'ledger-test-other'], ['name' => 'Other']);

    $this->ledger->allocate($this->tenant, 100);

    expect($this->ledger->balance(Tenant::withoutGlobalScopes()->find($other->id)))->toBe(0);

    CreditLedgerEntry::withoutTenancy()->where('tenant_id', $other->id)->forceDelete();
    $other->delete();
});
