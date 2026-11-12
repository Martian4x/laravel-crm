<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\PhoneRejection;

/**
 * The outcome of normalising one number.
 *
 * Deliberately not an exception: a bulk send normalises thousands of
 * numbers and needs to report the bad ones alongside the good, not abort on
 * the first one.
 */
final class PhoneNumber
{
    private function __construct(
        public readonly string $raw,
        public readonly ?string $e164 = null,
        public readonly ?PhoneRejection $rejection = null,
    ) {
    }

    public static function valid(string $raw, string $e164): self
    {
        return new self($raw, $e164);
    }

    public static function rejected(string $raw, PhoneRejection $rejection): self
    {
        return new self($raw, null, $rejection);
    }

    public function isValid(): bool
    {
        return $this->e164 !== null;
    }

    /**
     * Digits only, no '+' — the format Beem's dest_addr wants.
     */
    public function msisdn(): ?string
    {
        return $this->e164 ? ltrim($this->e164, '+') : null;
    }
}
