<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\PhoneRejection;

/**
 * One row of a pre-send summary: what will happen to this number, and why.
 */
final class PlannedRecipient
{
    public const SENDABLE = 'sendable';

    public const INVALID = 'invalid';

    public const OPTED_OUT = 'opted_out';

    public const DUPLICATE = 'duplicate';

    private function __construct(
        public readonly string $raw,
        public readonly string $outcome,
        public readonly ?string $e164 = null,
        public readonly ?PhoneRejection $rejection = null,
        public readonly ?string $recipientType = null,
        public readonly ?int $recipientId = null,
    ) {
    }

    public static function sendable(string $raw, string $e164, ?string $type = null, ?int $id = null): self
    {
        return new self($raw, self::SENDABLE, $e164, null, $type, $id);
    }

    public static function invalid(string $raw, PhoneRejection $rejection): self
    {
        return new self($raw, self::INVALID, null, $rejection);
    }

    public static function optedOut(string $raw, string $e164): self
    {
        return new self($raw, self::OPTED_OUT, $e164);
    }

    public static function duplicate(string $raw, string $e164): self
    {
        return new self($raw, self::DUPLICATE, $e164);
    }

    public function isSendable(): bool
    {
        return $this->outcome === self::SENDABLE;
    }
}
