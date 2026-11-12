<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\CreditType;
use Azimio\Messaging\Models\CreditLedgerEntry;
use Azimio\Messaging\Support\Reservation;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The only thing allowed to move platform credits.
 *
 * Two stores, deliberately:
 *
 *  - sms_credit_ledger is the TRUTH. Append-only; a correction is a new
 *    Adjustment row, never an edit.
 *  - tenants.sms_credits is a CACHE, so the balance shown on nearly every
 *    SMS screen does not aggregate the whole ledger.
 *
 * Reservation is a conditional UPDATE on the cache:
 *
 *     UPDATE tenants SET sms_credits = sms_credits - n
 *      WHERE id = ? AND sms_credits >= n
 *
 * The database decides. Two concurrent batches racing for the last credits
 * cannot both win, because the second UPDATE matches zero rows — no row
 * lock held across a round trip, no read-then-write gap to lose. This is
 * what 8.25 double-spend tests.
 *
 * Crash safety: if the process dies between reserve() and settle(), the
 * credits are held but no ledger row exists, so the cache sits BELOW the
 * ledger sum. reconcile() recomputes the cache from the ledger, which
 * returns those credits to the tenant. Drift therefore always resolves in
 * the tenant's favour, which is the right direction to fail for something
 * they paid for.
 */
class CreditLedger
{
    /**
     * Cached balance — what the UI reads.
     */
    public function balance(Tenant $tenant): int
    {
        return (int) Tenant::withoutGlobalScopes()->whereKey($tenant->id)->value('sms_credits');
    }

    /**
     * Authoritative balance, computed from the ledger.
     */
    public function trueBalance(Tenant $tenant): int
    {
        return CreditLedgerEntry::balanceFor($tenant->id);
    }

    /**
     * Hold credits for a send, or return null when the balance will not
     * cover it. Never partially reserves: a send either has its credits or
     * it does not.
     */
    public function reserve(
        Tenant $tenant,
        int $credits,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): ?Reservation {
        if ($credits <= 0) {
            return new Reservation($tenant->id, 0, $referenceType, $referenceId);
        }

        $claimed = DB::table('tenants')
            ->where('id', $tenant->id)
            ->where('sms_credits', '>=', $credits)
            ->update(['sms_credits' => DB::raw('sms_credits - '.$credits)]);

        return $claimed === 1
            ? new Reservation($tenant->id, $credits, $referenceType, $referenceId)
            : null;
    }

    /**
     * The provider accepted: turn the hold into a permanent debit.
     *
     * The cache was already decremented at reserve time, so this writes the
     * ledger row only — balance_after is the cache as it now stands.
     */
    public function settle(Reservation $reservation, ?string $note = null): ?CreditLedgerEntry
    {
        if ($reservation->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($reservation, $note) {
            $balance = (int) DB::table('tenants')
                ->where('id', $reservation->tenantId)
                ->lockForUpdate()
                ->value('sms_credits');

            return $this->write(
                $reservation->tenantId,
                CreditType::Debit,
                -$reservation->credits,
                $balance,
                $note,
                $reservation->referenceType,
                $reservation->referenceId,
            );
        });
    }

    /**
     * The provider refused before the message reached the network, so no
     * charge was incurred (decision 8.0.2). Put the credits back and write
     * NO ledger row — nothing ever left, so a debit-and-refund pair would
     * be noise in an audit rather than information.
     */
    public function release(Reservation $reservation): void
    {
        if ($reservation->isEmpty()) {
            return;
        }

        DB::table('tenants')
            ->where('id', $reservation->tenantId)
            ->update(['sms_credits' => DB::raw('sms_credits + '.$reservation->credits)]);
    }

    /**
     * Platform admin grants credits (8.15/8.16).
     */
    public function allocate(
        Tenant $tenant,
        int $credits,
        ?int $actorId = null,
        ?string $note = null,
        CreditType $type = CreditType::Allocation,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): CreditLedgerEntry {
        return DB::transaction(function () use ($tenant, $credits, $actorId, $note, $type, $referenceType, $referenceId) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['sms_credits' => DB::raw('sms_credits + '.$credits)]);

            $balance = (int) DB::table('tenants')->where('id', $tenant->id)->value('sms_credits');

            return $this->write(
                $tenant->id,
                $type,
                $credits,
                $balance,
                $note,
                $referenceType,
                $referenceId,
                $actorId,
            );
        });
    }

    /**
     * Recompute the cache from the ledger and report the drift it found.
     * Positive drift means the cache was understating the balance, which is
     * what an abandoned reservation looks like.
     */
    public function reconcile(Tenant $tenant): int
    {
        $true = $this->trueBalance($tenant);
        $cached = $this->balance($tenant);

        if ($true !== $cached) {
            DB::table('tenants')->where('id', $tenant->id)->update(['sms_credits' => $true]);
        }

        return $true - $cached;
    }

    private function write(
        int $tenantId,
        CreditType $type,
        int $credits,
        int $balanceAfter,
        ?string $note,
        ?string $referenceType,
        ?int $referenceId,
        ?int $actorId = null,
    ): CreditLedgerEntry {
        $entry = new CreditLedgerEntry;

        $entry->forceFill([
            'tenant_id'      => $tenantId,
            'type'           => $type->value,
            'credits'        => $credits,
            'balance_after'  => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'created_by'     => $actorId,
            'note'           => $note,
        ])->save();

        return $entry;
    }
}
