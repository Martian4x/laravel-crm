<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\CreditRequestStatus;
use Azimio\Messaging\Enums\CreditType;
use Azimio\Messaging\Models\CreditRequest;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The credit request loop: a tenant asks (8.13), a platform admin decides
 * (8.16).
 *
 * The one rule that matters here: a decision and the credits it grants are
 * written in ONE transaction. A request marked approved with no allocation
 * behind it, or credits granted with no record of who approved them, are
 * both unexplainable states in a billing dispute — so neither can happen
 * halfway.
 */
class CreditRequests
{
    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * Tenant asks for credits.
     */
    public function open(Tenant $tenant, int $credits, ?string $reason, ?int $requestedBy): CreditRequest
    {
        $request = new CreditRequest;

        $request->forceFill([
            'tenant_id'         => $tenant->id,
            'requested_by'      => $requestedBy,
            'credits_requested' => $credits,
            'reason'            => $reason,
            'status'            => CreditRequestStatus::Pending->value,
        ])->save();

        return $request;
    }

    /**
     * Grant some or all of a request.
     *
     * $granted below the amount asked for is a partial approval, which is
     * recorded as such rather than silently rounded up or down — a tenant
     * who asked for 5,000 and got 1,000 should see that, not a bare
     * "approved".
     */
    public function approve(
        CreditRequest $request,
        int $granted,
        ?int $reviewedBy,
        ?string $note = null,
    ): CreditRequest {
        return DB::transaction(function () use ($request, $granted, $reviewedBy, $note) {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($request->tenant_id);

            $this->ledger->allocate(
                $tenant,
                $granted,
                $reviewedBy,
                $note ?: trans('messaging::app.requests.ledger-note', ['id' => $request->id]),
                CreditType::Allocation,
                CreditRequest::class,
                $request->id,
            );

            $request->forceFill([
                'status' => $granted >= $request->credits_requested
                    ? CreditRequestStatus::Approved->value
                    : CreditRequestStatus::PartiallyApproved->value,
                'credits_granted' => $granted,
                'reviewed_by'     => $reviewedBy,
                'reviewed_at'     => now(),
                'admin_note'      => $note,
            ])->save();

            return $request;
        });
    }

    public function reject(CreditRequest $request, ?int $reviewedBy, ?string $note = null): CreditRequest
    {
        $request->forceFill([
            'status'          => CreditRequestStatus::Rejected->value,
            'credits_granted' => 0,
            'reviewed_by'     => $reviewedBy,
            'reviewed_at'     => now(),
            'admin_note'      => $note,
        ])->save();

        return $request;
    }

    /**
     * Whether a tenant already has an open request — used to stop a queue
     * filling with duplicates from someone clicking twice.
     */
    public function hasOpenRequest(): bool
    {
        return CreditRequest::query()
            ->where('status', CreditRequestStatus::Pending->value)
            ->exists();
    }
}
