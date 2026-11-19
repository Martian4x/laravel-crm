<?php

namespace Azimio\Messaging\Support;

/**
 * Credits held for a send that has not been submitted yet.
 *
 * Decision 8.0.2 is reserve-then-settle: credits leave the balance when the
 * send starts, but the ledger debit is only written once the provider
 * accepts. A reservation is therefore a promise, not a transaction — it is
 * either settled into a debit or released back.
 */
final class Reservation
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $credits,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->credits <= 0;
    }
}
