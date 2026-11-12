<?php

namespace Azimio\Messaging\Support;

/**
 * A provider account's remaining balance.
 *
 * This is the TENANT's balance at their own provider, which is a different
 * thing from platform credits in sms_credit_ledger. Only own-provider
 * tenants have one; in platform mode the figure that matters is
 * tenants.sms_credits.
 *
 * Beem reports credit_balance as a float with no currency, meaning SMS
 * credits rather than money, so $unit defaults to 'credits'.
 */
final class Balance
{
    public function __construct(
        public readonly float $amount,
        public readonly string $unit = 'credits',
        public readonly array $raw = [],
    ) {
    }

    public function isLow(?int $threshold = null): bool
    {
        $threshold ??= (int) config('messaging.credits.low_balance_threshold', 50);

        return $this->amount <= $threshold;
    }
}
