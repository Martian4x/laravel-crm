<?php

namespace Azimio\Messaging\Enums;

/**
 * Why a credit ledger row exists (sms_credit_ledger.type).
 *
 * The ledger is append-only, so a mistake is corrected with an Adjustment
 * row rather than by editing history.
 */
enum CreditType: string
{
    case Allocation = 'allocation';
    case Purchase = 'purchase';
    case Debit = 'debit';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Expiry = 'expiry';

    /**
     * Whether rows of this type normally carry a positive amount. Adjustment
     * is deliberately absent: it can go either way, which is the point of it.
     */
    public function isIncrease(): bool
    {
        return in_array($this, [
            self::Allocation,
            self::Purchase,
            self::Refund,
        ], true);
    }
}
