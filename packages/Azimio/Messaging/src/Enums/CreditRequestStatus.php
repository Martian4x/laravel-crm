<?php

namespace Azimio\Messaging\Enums;

/**
 * Platform admin's decision on a tenant's credit request (8.16).
 */
enum CreditRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Whether this outcome should have written an allocation to the ledger.
     */
    public function grantedCredits(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyApproved], true);
    }
}
