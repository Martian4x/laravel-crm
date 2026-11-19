<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\SendFailure;

/**
 * What a provider said about one submission.
 *
 * Note this is per REQUEST, not per recipient: Beem returns a single
 * request_id covering every recipient in the call, and no per-message id.
 * Delivery status is later looked up by (request_id, dest_addr), which is
 * why sms_messages carries both provider_request_id and dest_addr.
 */
final class SendResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $requestId = null,
        public readonly ?SendFailure $failure = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        public readonly int $accepted = 0,
        public readonly int $rejected = 0,
        public readonly int $duplicates = 0,
        public readonly int $optedOut = 0,
        public readonly array $raw = [],
    ) {}

    public static function accepted(
        ?string $requestId,
        int $accepted = 0,
        int $rejected = 0,
        int $duplicates = 0,
        int $optedOut = 0,
        array $raw = [],
    ): self {
        return new self(
            successful: true,
            requestId: $requestId,
            accepted: $accepted,
            rejected: $rejected,
            duplicates: $duplicates,
            optedOut: $optedOut,
            raw: $raw,
        );
    }

    public static function failed(
        SendFailure $failure,
        ?string $providerCode = null,
        ?string $providerMessage = null,
        array $raw = [],
    ): self {
        return new self(
            successful: false,
            failure: $failure,
            providerCode: $providerCode,
            providerMessage: $providerMessage,
            raw: $raw,
        );
    }

    public function failedBecauseOutOfBalance(): bool
    {
        return ! $this->successful && $this->failure?->isBalanceProblem() === true;
    }

    public function isTransientFailure(): bool
    {
        return ! $this->successful && $this->failure?->isTransient() === true;
    }
}
