<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\SmsStatus;

/**
 * One delivery status, as returned by polling the provider.
 *
 * Beem has NO delivery-report callback for the REST SMS API — status is
 * fetched by GET with (request_id, dest_addr), and their docs ask callers
 * to wait at least five minutes after submitting. See 8.19.
 */
final class DeliveryReport
{
    public function __construct(
        public readonly string $destAddr,
        public readonly SmsStatus $status,
        public readonly ?string $requestId = null,
        public readonly array $raw = [],
    ) {}

    /**
     * PENDING means the network has not resolved it yet, so the poller must
     * come back rather than write a terminal status.
     */
    public function isPending(): bool
    {
        return ! $this->status->isTerminal();
    }
}
