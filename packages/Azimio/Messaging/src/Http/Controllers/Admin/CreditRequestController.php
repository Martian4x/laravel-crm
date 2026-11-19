<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Mail\CreditRequestSubmitted;
use Azimio\Messaging\Models\CreditRequest;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Services\CreditRequests;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\User;

/**
 * Tenant side of the credit request loop (8.13).
 */
class CreditRequestController extends Controller
{
    public function __construct(
        private readonly CreditRequests $requests,
        private readonly CreditLedger $ledger,
    ) {}

    public function index()
    {
        $tenant = $this->tenant();

        return view('messaging::admin.credits', [
            'tenant'    => $tenant,
            'balance'   => $this->ledger->balance($tenant),
            'threshold' => (int) ($tenant->sms_low_credit_threshold ?: config('messaging.credits.low_balance_threshold')),
            'requests'  => CreditRequest::query()->with('reviewedBy')->latest('id')->paginate(20),
            'hasOpen'   => $this->requests->hasOpenRequest(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'credits_requested' => 'required|integer|min:1|max:1000000',
            'reason'            => 'nullable|string|max:1000',
        ]);

        // One open request at a time: a queue full of duplicates from
        // someone clicking twice wastes an admin's attention.
        if ($this->requests->hasOpenRequest()) {
            session()->flash('error', trans('messaging::app.requests.already-open'));

            return redirect()->route('admin.sms.credits.index');
        }

        $tenant = $this->tenant();

        $creditRequest = $this->requests->open(
            $tenant,
            (int) $validated['credits_requested'],
            $validated['reason'] ?? null,
            auth()->id(),
        );

        $this->notifySuperAdmins($tenant, $creditRequest);

        session()->flash('success', trans('messaging::app.requests.submitted'));

        return redirect()->route('admin.sms.credits.index');
    }

    /**
     * Tell the platform admins there is something to review.
     *
     * Failures are logged and swallowed: a mail outage must not lose the
     * tenant's request, which is already safely in the database.
     */
    private function notifySuperAdmins(Tenant $tenant, CreditRequest $creditRequest): void
    {
        try {
            $admins = User::withoutTenancy()
                ->where('is_super_admin', 1)
                ->where('status', 1)
                ->pluck('email')
                ->filter()
                ->all();

            if ($admins === []) {
                return;
            }

            Mail::to($admins)->queue(new CreditRequestSubmitted($tenant, $creditRequest));
        } catch (\Throwable $e) {
            Log::error('[sms] could not notify super admins of a credit request', [
                'request_id' => $creditRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail(tenant_id());
    }
}
