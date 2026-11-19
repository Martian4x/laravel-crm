<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\Encoding;
use DateTimeInterface;

/**
 * One message body addressed to one or more recipients.
 *
 * There is deliberately no separate "single" and "bulk" shape: Beem's
 * /v1/send takes one body plus a recipients array, so a single send is just
 * this with one recipient. Modelling it any other way would invent a
 * distinction the provider does not have.
 *
 * The pipeline (8.5) still writes one sms_messages row per recipient — the
 * whole submission shares one provider request_id, which is how delivery
 * reports are later looked up.
 */
final class OutboundMessage
{
    /**
     * @param  Recipient[]  $recipients
     */
    public function __construct(
        public readonly string $senderName,
        public readonly string $body,
        public readonly array $recipients,
        public readonly Encoding $encoding = Encoding::Gsm7,
        public readonly ?DateTimeInterface $scheduledAt = null,
        public readonly ?string $jobName = null,
    ) {}

    public function recipientCount(): int
    {
        return count($this->recipients);
    }

    /**
     * Split into chunks a provider will accept in one call. Beem documents a
     * maximum of 1000 recipients per request (error code 114).
     *
     * @return self[]
     */
    public function chunk(int $size): array
    {
        return array_map(
            fn (array $slice) => new self(
                $this->senderName,
                $this->body,
                $slice,
                $this->encoding,
                $this->scheduledAt,
                $this->jobName,
            ),
            array_chunk($this->recipients, max(1, $size))
        );
    }
}
