<?php

namespace Azimio\Messaging\Enums;

/**
 * Lifecycle of a single message (sms_messages.status).
 *
 * 'submitted' means the provider accepted it and we were charged — that is
 * the point credits settle (decision 8.0.2). Anything that fails BEFORE
 * submission never debits, so it is refund-free by construction.
 */
enum SmsStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case FailedOwnProvider = 'failed_own_provider';
    case Rejected = 'rejected';
    case Undeliverable = 'undeliverable';

    /**
     * No further transition is expected, so a batch may stop waiting on it.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Failed,
            self::FailedOwnProvider,
            self::Rejected,
            self::Undeliverable,
        ], true);
    }

    /**
     * The provider took the message, so the operator has charged us.
     * Credits for these are settled and never refunded (8.0.2).
     */
    public function wasSubmitted(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::Delivered,
            self::Undeliverable,
        ], true);
    }

    /**
     * Failed before reaching the network, so any reservation is released
     * rather than debited.
     */
    public function releasesReservation(): bool
    {
        return in_array($this, [
            self::Failed,
            self::FailedOwnProvider,
            self::Rejected,
        ], true);
    }
}
