<?php

namespace Azimio\Messaging\Http\Controllers\Super;

use Azimio\Messaging\Enums\CreditRequestStatus;
use Azimio\Messaging\Mail\CreditRequestReviewed;
use Azimio\Messaging\Models\CreditRequest;
use Azimio\Messaging\Services\CreditRequests;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\User;

/**
 * Platform-admin review queue (8.16).
 *
 * Runs outside tenant context, so every query bypasses the tenant scope
 * explicitly.
 */
class CreditRequestQueueController extends Controller
{
    public function __construct(private readonly CreditRequests $requests) {}

    public function index(Request $request)
    {
        $status = $request->input('status', CreditRequestStatus::Pending->value);

        return view('messaging::super.requests', [
            'requests' => CreditRequest::withoutTenancy()
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->with(['tenant', 'requestedBy', 'reviewedBy'])
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'status'       => $status,
            'statuses'     => CreditRequestStatus::cases(),
            'pendingCount' => CreditRequest::withoutTenancy()
                ->where('status', CreditRequestStatus::Pending->value)->count(),
        ]);
    }

    /**
     * Approve in full, approve partially, or reject.
     *
     * The granted amount is taken from the form rather than assumed, which
     * is what makes partial approval a first-class outcome instead of an
     * all-or-nothing choice.
     */
    public function review(int $id, Request $request)
    {
        $validated = $request->validate([
            'decision'        => 'required|in:approve,reject',
            'credits_granted' => 'nullable|integer|min:0|max:1000000',
            'admin_note'      => 'nullable|string|max:500',
        ]);

        $creditRequest = CreditRequest::withoutTenancy()->findOrFail($id);

        if ($creditRequest->status->isDecided()) {
            session()->flash('error', trans('messaging::app.requests.already-decided'));

            return redirect()->route('super.sms.requests.index');
        }

        if ($validated['decision'] === 'reject') {
            $this->requests->reject($creditRequest, auth()->id(), $validated['admin_note'] ?? null);

            $this->notifyTenant($creditRequest);

            session()->flash('success', trans('messaging::app.requests.rejected'));

            return redirect()->route('super.sms.requests.index');
        }

        $granted = (int) ($validated['credits_granted'] ?? $creditRequest->credits_requested);

        if ($granted < 1) {
            session()->flash('error', trans('messaging::app.requests.grant-too-small'));

            return redirect()->route('super.sms.requests.index');
        }

        // Decision and allocation land in one transaction — see the service.
        $this->requests->approve(
            $creditRequest,
            $granted,
            auth()->id(),
            $validated['admin_note'] ?? null,
        );

        $this->notifyTenant($creditRequest->fresh());

        session()->flash('success', trans('messaging::app.requests.approved', ['credits' => $granted]));

        return redirect()->route('super.sms.requests.index');
    }

    /**
     * Mail failures are logged, never allowed to undo a decision that is
     * already recorded.
     */
    private function notifyTenant(CreditRequest $creditRequest): void
    {
        try {
            $tenant = Tenant::withoutGlobalScopes()->find($creditRequest->tenant_id);

            $email = $creditRequest->requested_by
                ? User::withoutTenancy()->whereKey($creditRequest->requested_by)->value('email')
                : null;

            if (! $tenant || ! $email) {
                return;
            }

            Mail::to($email)->queue(new CreditRequestReviewed($tenant, $creditRequest));
        } catch (\Throwable $e) {
            Log::error('[sms] could not notify a tenant of a credit decision', [
                'request_id' => $creditRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
