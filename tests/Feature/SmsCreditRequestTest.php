<?php

use Azimio\Messaging\Enums\CreditRequestStatus;
use Azimio\Messaging\Mail\CreditRequestReviewed;
use Azimio\Messaging\Mail\CreditRequestSubmitted;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\CreditRequest;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\User;

/**
 * Phase 8.13 / 8.16 — the credit request loop.
 *
 * The decisive behaviour is that an approval and the credits it grants are
 * one atomic act: a request marked approved with nothing allocated, or
 * credits granted with no record of who approved them, are both states
 * nobody could explain in a billing dispute.
 */
function reqSuperAdmin(): User
{
    return User::withoutTenancy()->where('is_super_admin', 1)->firstOrFail();
}

function reqTenantAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function reqTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

afterEach(function () {
    CreditRequest::withoutTenancy()->where('tenant_id', reqTenant()->id)->forceDelete();
    CreditLedgerEntry::withoutTenancy()->where('tenant_id', reqTenant()->id)->forceDelete();
    Tenant::withoutGlobalScopes()->update(['sms_credits' => 0]);
});

function openRequest(int $credits = 500): CreditRequest
{
    $request = new CreditRequest;

    $request->forceFill([
        'tenant_id'         => reqTenant()->id,
        'requested_by'      => reqTenantAdmin()->id,
        'credits_requested' => $credits,
        'reason'            => 'Campaign next week',
        'status'            => CreditRequestStatus::Pending->value,
    ])->save();

    return $request;
}

it('renders the tenant credits page', function () {
    test()->actingAs(reqTenantAdmin())
        ->get(route('admin.sms.credits.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.requests.new-title'));
});

it('lets a tenant open a request and notifies the platform admins', function () {
    Mail::fake();

    test()->actingAs(reqTenantAdmin())
        ->post(route('admin.sms.credits.store'), [
            'credits_requested' => 500,
            'reason'            => 'Campaign next week',
        ])
        ->assertRedirect(route('admin.sms.credits.index'))
        ->assertSessionHas('success');

    $request = CreditRequest::withoutTenancy()->latest('id')->first();

    expect($request->credits_requested)->toBe(500)
        ->and($request->status)->toBe(CreditRequestStatus::Pending)
        ->and($request->tenant_id)->toBe(reqTenant()->id);

    Mail::assertQueued(CreditRequestSubmitted::class);
});

it('refuses a second request while one is still open', function () {
    // A queue full of duplicates from someone clicking twice wastes an
    // admin's attention.
    openRequest();

    test()->actingAs(reqTenantAdmin())
        ->post(route('admin.sms.credits.store'), ['credits_requested' => 100])
        ->assertSessionHas('error', trans('messaging::app.requests.already-open'));

    expect(CreditRequest::withoutTenancy()->where('tenant_id', reqTenant()->id)->count())->toBe(1);
});

it('validates the requested amount', function () {
    test()->actingAs(reqTenantAdmin())
        ->post(route('admin.sms.credits.store'), ['credits_requested' => 0])
        ->assertSessionHasErrors('credits_requested');
});

it('shows pending requests in the admin queue', function () {
    openRequest();

    test()->actingAs(reqSuperAdmin())
        ->get(route('super.sms.requests.index'))
        ->assertOK()
        ->assertSee('Campaign next week')
        ->assertSee(trans('messaging::app.requests.approve'));
});

it('hides the review queue from a tenant user', function () {
    test()->actingAs(reqTenantAdmin())
        ->get(route('super.sms.requests.index'))
        ->assertNotFound();
});

it('refuses a tenant posting their own approval', function () {
    // The failure that would actually cost money.
    $request = openRequest();

    test()->actingAs(reqTenantAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision'        => 'approve',
            'credits_granted' => 99999,
        ])
        ->assertNotFound();

    expect($request->fresh()->status)->toBe(CreditRequestStatus::Pending)
        ->and(app(CreditLedger::class)->balance(reqTenant()))->toBe(0);
});

it('approves in full and allocates the credits atomically', function () {
    Mail::fake();

    $request = openRequest(500);

    test()->actingAs(reqSuperAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision'        => 'approve',
            'credits_granted' => 500,
            'admin_note'      => 'Paid by mobile money',
        ])
        ->assertRedirect(route('super.sms.requests.index'))
        ->assertSessionHas('success');

    $fresh = $request->fresh();
    $entry = CreditLedgerEntry::withoutTenancy()->latest('id')->first();

    expect($fresh->status)->toBe(CreditRequestStatus::Approved)
        ->and($fresh->credits_granted)->toBe(500)
        ->and($fresh->reviewed_by)->toBe(reqSuperAdmin()->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        // The credits exist, and the ledger row points back at the request.
        ->and(app(CreditLedger::class)->balance(reqTenant()))->toBe(500)
        ->and($entry->credits)->toBe(500)
        ->and($entry->reference_type)->toBe(CreditRequest::class)
        ->and($entry->reference_id)->toBe($request->id);

    Mail::assertQueued(CreditRequestReviewed::class);
});

it('records a smaller grant as a partial approval, not a full one', function () {
    Mail::fake();

    $request = openRequest(5000);

    test()->actingAs(reqSuperAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision'        => 'approve',
            'credits_granted' => 1000,
        ])
        ->assertSessionHas('success');

    // A tenant who asked for 5,000 and got 1,000 should see that, rather
    // than a bare "approved".
    expect($request->fresh()->status)->toBe(CreditRequestStatus::PartiallyApproved)
        ->and($request->fresh()->credits_granted)->toBe(1000)
        ->and(app(CreditLedger::class)->balance(reqTenant()))->toBe(1000);
});

it('rejects without allocating anything', function () {
    Mail::fake();

    $request = openRequest();

    test()->actingAs(reqSuperAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision'   => 'reject',
            'admin_note' => 'No payment received',
        ])
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toBe(CreditRequestStatus::Rejected)
        ->and($request->fresh()->credits_granted)->toBe(0)
        ->and(app(CreditLedger::class)->balance(reqTenant()))->toBe(0)
        ->and(CreditLedgerEntry::withoutTenancy()->where('tenant_id', reqTenant()->id)->count())->toBe(0);
});

it('cannot decide the same request twice', function () {
    Mail::fake();

    $request = openRequest(500);

    test()->actingAs(reqSuperAdmin())->post(route('super.sms.requests.review', $request->id), [
        'decision' => 'approve', 'credits_granted' => 500,
    ]);

    // A double submit must not allocate twice.
    test()->actingAs(reqSuperAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision' => 'approve', 'credits_granted' => 500,
        ])
        ->assertSessionHas('error', trans('messaging::app.requests.already-decided'));

    expect(app(CreditLedger::class)->balance(reqTenant()))->toBe(500);
});

it('refuses an approval of zero credits', function () {
    $request = openRequest();

    test()->actingAs(reqSuperAdmin())
        ->post(route('super.sms.requests.review', $request->id), [
            'decision' => 'approve', 'credits_granted' => 0,
        ])
        ->assertSessionHas('error', trans('messaging::app.requests.grant-too-small'));

    expect($request->fresh()->status)->toBe(CreditRequestStatus::Pending);
});

it('shows the tenant their decided request and the note', function () {
    Mail::fake();

    $request = openRequest(500);

    test()->actingAs(reqSuperAdmin())->post(route('super.sms.requests.review', $request->id), [
        'decision' => 'approve', 'credits_granted' => 250, 'admin_note' => 'Half for now',
    ]);

    test()->actingAs(reqTenantAdmin())
        ->get(route('admin.sms.credits.index'))
        ->assertOK()
        ->assertSee('Half for now')
        ->assertSee(trans('messaging::app.requests.status.partially-approved'));
});
