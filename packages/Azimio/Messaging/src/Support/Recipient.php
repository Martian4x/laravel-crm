<?php

namespace Azimio\Messaging\Support;

/**
 * One destination in an outbound send.
 *
 * $reference is our own sms_messages.id, echoed back by the provider so a
 * response can be matched to the row it belongs to. Beem calls this
 * recipient_id and requires it to be unique within a request.
 *
 * $destAddr is stored in E.164 (+255...) because that is what our schema
 * holds; each driver converts to whatever its wire format needs. Beem wants
 * no leading '+' and no leading zero, so BeemDriver strips it.
 */
final class Recipient
{
    public function __construct(
        public readonly int $reference,
        public readonly string $destAddr,
    ) {
    }

    /**
     * Digits only — no '+', no spaces. Beem's documented dest_addr format.
     */
    public function msisdn(): string
    {
        return ltrim(preg_replace('/\D+/', '', $this->destAddr), '0') ?: '';
    }
}
