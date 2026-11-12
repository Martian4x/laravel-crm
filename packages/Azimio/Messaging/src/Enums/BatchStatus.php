<?php

namespace Azimio\Messaging\Enums;

/**
 * Lifecycle of a bulk send (sms_batches.status).
 */
enum BatchStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::PartiallyFailed,
            self::Cancelled,
        ], true);
    }

    /**
     * A batch may only be cancelled before the provider has everything.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Sending], true);
    }
}
