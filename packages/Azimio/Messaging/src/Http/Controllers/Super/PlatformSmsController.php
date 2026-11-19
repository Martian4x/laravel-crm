<?php

namespace Azimio\Messaging\Http\Controllers\Super;

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Enums\CreditType;
use Azimio\Messaging\Enums\SenderProfileStatus;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Platform SMS control (8.14 + 8.15).
 *
 * Everything here runs OUTSIDE tenant context — the super panel sits behind
 * EnsureSuperAdmin and deliberately reads across every tenant, so each query
 * uses withoutTenancy()/withoutGlobalScopes() explicitly rather than relying
 * on a scope that is not active.
 */
class PlatformSmsController extends Controller
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly DriverManager $drivers,
    ) {}

    /**
     * Platform sender profile, its provider balance, and credit usage per
     * tenant (8.14).
     */
    public function index()
    {
        $profile = SenderProfile::platform();

        $tenants = Tenant::withoutGlobalScopes()->orderBy('name')->get()->map(function (Tenant $tenant) {
            $tenant->ledger_balance = CreditLedgerEntry::balanceFor($tenant->id);

            // Drift is the difference between the cache and the ledger. Any
            // non-zero value is an abandoned reservation or a bug, and the
            // reconcile action resolves it in the tenant's favour.
            $tenant->drift = $tenant->ledger_balance - (int) $tenant->sms_credits;

            $tenant->sent_count = Message::withoutTenancy()
                ->where('tenant_id', $tenant->id)
                ->where('cost_source', 'platform')
                ->count();

            $tenant->spent_credits = (int) Message::withoutTenancy()
                ->where('tenant_id', $tenant->id)
                ->sum('credits_charged');

            return $tenant;
        });

        return view('messaging::super.index', [
            'profile'         => $profile,
            'providerBalance' => $profile ? $this->drivers->for($profile)->balance()?->amount : null,
            'tenants'         => $tenants,
            'platformSender'  => config('messaging.platform.sender_name'),
            'drivers'         => ['beem' => 'Beem Africa', 'log' => 'Log (development only)'],
            'platformPaused'  => $profile !== null && ! $profile->canSend(),
            'monthlyUsage'    => $this->monthlyUsage(),
            'totalAllocated'  => (int) CreditLedgerEntry::withoutTenancy()
                ->whereIn('type', [CreditType::Allocation->value, CreditType::Purchase->value])
                ->sum('credits'),
        ]);
    }

    /**
     * Save the platform's own provider credentials (8.14).
     *
     * This is the AZIMIOCRM profile every platform-mode tenant sends
     * through, so it is the NULL-tenant row — created here rather than by
     * any tenant-facing code.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver'      => 'required|in:beem,log',
            'sender_name' => 'required|string|max:11',
            'api_key'     => 'nullable|string|max:255',
            'secret_key'  => 'nullable|string|max:255',
        ]);

        $profile = SenderProfile::platform() ?? new SenderProfile;

        $credentials = $profile->credentials ?? [];

        // Blank fields mean "leave as is", so an admin editing the sender
        // name does not have to re-enter the secret.
        foreach (['api_key', 'secret_key'] as $field) {
            if (! empty($validated[$field])) {
                $credentials[$field] = $validated[$field];
            }
        }

        $profile->forceFill([
            'tenant_id'   => null,
            'label'       => 'Platform',
            'sender_name' => $validated['sender_name'],
            'driver'      => $validated['driver'],
            'credentials' => $credentials,
            'status'      => SenderProfileStatus::Approved->value,
            'is_default'  => true,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ])->save();

        session()->flash('success', trans('messaging::app.super.saved'));

        return redirect()->route('super.sms.index');
    }

    /**
     * Allocate, deduct or adjust a tenant's credits (8.15).
     *
     * A note is mandatory. These rows are the evidence in a billing dispute
     * and "someone added 5,000 credits in March" is not evidence.
     */
    public function allocate(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'credits'   => 'required|integer|not_in:0',
            'type'      => 'required|in:allocation,purchase,adjustment,expiry',
            'note'      => 'required|string|max:500',
        ]);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($validated['tenant_id']);

        $credits = (int) $validated['credits'];
        $type = CreditType::from($validated['type']);

        // A deduction must not drive the balance negative: a tenant cannot
        // owe credits, and a negative balance would make every reservation
        // fail in a way nobody could explain.
        if ($credits < 0 && $this->ledger->balance($tenant) + $credits < 0) {
            session()->flash('error', trans('messaging::app.super.would-go-negative', [
                'balance' => $this->ledger->balance($tenant),
            ]));

            return redirect()->route('super.sms.index');
        }

        $this->ledger->allocate(
            $tenant,
            $credits,
            auth()->id(),
            $validated['note'],
            $type,
        );

        session()->flash('success', trans('messaging::app.super.allocated', [
            'credits' => $credits,
            'tenant'  => $tenant->name,
        ]));

        return redirect()->route('super.sms.index');
    }

    /**
     * Full ledger for one tenant (8.15).
     */
    public function ledger(int $id)
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($id);

        return view('messaging::super.ledger', [
            'tenant'  => $tenant,
            'entries' => CreditLedgerEntry::withoutTenancy()
                ->where('tenant_id', $tenant->id)
                ->with('createdBy')
                ->latest('id')
                ->paginate(50),
            'cached'  => (int) $tenant->sms_credits,
            'true'    => CreditLedgerEntry::balanceFor($tenant->id),
        ]);
    }

    /**
     * Recompute a tenant's cached balance from the ledger and report the
     * drift (8.15).
     *
     * Drift is normally an abandoned reservation — the process died between
     * reserve and settle, so credits are held with no ledger row behind
     * them. Reconciling returns them, which is the right way to fail for
     * something the tenant paid for.
     */
    public function reconcile(int $id)
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($id);

        $drift = $this->ledger->reconcile($tenant);

        session()->flash(
            'success',
            $drift === 0
                ? trans('messaging::app.super.no-drift')
                : trans('messaging::app.super.reconciled', ['drift' => $drift])
        );

        return redirect()->route('super.sms.ledger', $id);
    }

    /**
     * Cross-tenant message log (part of 8.18, built here because the data
     * is already to hand).
     */
    public function messages(Request $request)
    {
        $messages = Message::withoutTenancy()
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate(100)
            ->withQueryString();

        return view('messaging::super.messages', [
            'messages' => $messages,
            'tenants'  => Tenant::withoutGlobalScopes()->orderBy('name')->get(),
            'filters'  => [
                'tenant_id' => $request->input('tenant_id'),
                'status'    => $request->input('status'),
            ],
        ]);
    }

    /**
     * Pause or resume ALL platform-mode sending (8.18).
     *
     * Implemented by flipping the platform profile's own status rather than
     * introducing a separate setting: SenderProfileStatus::Disabled already
     * stops SendSmsJob sending from that profile, so the switch reuses a
     * gate that is already enforced and tested, and cannot drift out of
     * step with it.
     *
     * Own-provider tenants are deliberately unaffected — it is their credit
     * and their provider account, not ours to switch off.
     */
    public function togglePause()
    {
        $profile = SenderProfile::platform();

        if (! $profile) {
            session()->flash('error', trans('messaging::app.super.no-platform-profile'));

            return redirect()->route('super.sms.index');
        }

        $paused = ! $profile->canSend();

        $profile->forceFill([
            'status' => $paused
                ? SenderProfileStatus::Approved->value
                : SenderProfileStatus::Disabled->value,
        ])->save();

        session()->flash('success', trans($paused
            ? 'messaging::app.super.resumed'
            : 'messaging::app.super.paused'));

        return redirect()->route('super.sms.index');
    }

    /**
     * Messages and credits per tenant for the last six months (8.18).
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlyUsage(): array
    {
        return Message::withoutTenancy()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as messages, sum(credits_charged) as credits")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->where('direction', 'outbound')
            ->groupBy('month')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($row) => [
                'month'    => $row->month,
                'messages' => (int) $row->messages,
                'credits'  => (int) $row->credits,
            ])
            ->all();
    }
}
