<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\Encoding;

/**
 * How a body will be split on the wire, and therefore what it costs.
 *
 * One credit is one segment (decision 8.0.1), so this object is the input
 * to every price shown and every ledger entry written.
 */
final class SegmentCount
{
    public function __construct(
        public readonly Encoding $encoding,
        public readonly int $length,
        public readonly int $segments,
        public readonly int $remainingInLastSegment,
    ) {}

    /**
     * Credits for sending this body to one recipient.
     */
    public function creditsPerRecipient(): int
    {
        return $this->segments;
    }

    public function creditsFor(int $recipients): int
    {
        return $this->segments * max(0, $recipients);
    }

    public function isMultipart(): bool
    {
        return $this->segments > 1;
    }

    /**
     * True when a single character outside the GSM alphabet has forced the
     * whole message to UCS-2, cutting capacity from 160 to 70. Worth
     * surfacing in the compose UI: one stray curly quote can triple a bill.
     */
    public function isUnicode(): bool
    {
        return $this->encoding === Encoding::Ucs2;
    }
}
