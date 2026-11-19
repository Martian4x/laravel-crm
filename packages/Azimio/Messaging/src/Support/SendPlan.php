<?php

namespace Azimio\Messaging\Support;

use Azimio\Messaging\Enums\SmsMode;
use Carbon\CarbonImmutable;

/**
 * What a send WOULD do, worked out before anything is written or charged.
 *
 * This is the object behind the bulk confirmation screen (8.8), and the
 * same object the pipeline acts on — so the number a user approves is
 * literally the number that gets charged, not a separate estimate that can
 * drift from it.
 *
 * $body is the FINAL text, including any unsubscribe notice appended for
 * marketing (8.12). It has to live here rather than being re-derived at
 * dispatch, or the notice would be absent from the price shown but present
 * in what is sent.
 */
final class SendPlan
{
    /**
     * @param  PlannedRecipient[]  $recipients
     */
    public function __construct(
        public readonly string $body,
        public readonly array $recipients,
        public readonly SegmentCount $segments,
        public readonly SmsMode $mode,
        public readonly int $creditsRequired,
        public readonly int $balance,
        public readonly ?CarbonImmutable $deferredUntil = null,
        public readonly ?int $dailyCapRemaining = null,
    ) {
    }

    /** @return PlannedRecipient[] */
    public function sendable(): array
    {
        return array_values(array_filter($this->recipients, fn ($r) => $r->isSendable()));
    }

    /** @return PlannedRecipient[] */
    public function excluded(string $outcome): array
    {
        return array_values(array_filter($this->recipients, fn ($r) => $r->outcome === $outcome));
    }

    public function sendableCount(): int
    {
        return count($this->sendable());
    }

    /**
     * Own-provider sends draw on the tenant's own account, so the platform
     * balance is irrelevant to whether they may proceed.
     */
    public function hasSufficientCredits(): bool
    {
        return ! $this->mode->usesPlatformCredits() || $this->balance >= $this->creditsRequired;
    }

    public function shortfall(): int
    {
        return max(0, $this->creditsRequired - $this->balance);
    }

    public function isDeferred(): bool
    {
        return $this->deferredUntil !== null;
    }

    public function hasAnythingToSend(): bool
    {
        return $this->sendableCount() > 0;
    }

    /**
     * Whether today's cap would be exceeded. null means uncapped.
     */
    public function exceedsDailyCap(): bool
    {
        return $this->dailyCapRemaining !== null
            && $this->sendableCount() > $this->dailyCapRemaining;
    }

    /**
     * Everything that must be true before this send may proceed.
     */
    public function isSendable(): bool
    {
        return $this->hasAnythingToSend()
            && $this->hasSufficientCredits()
            && ! $this->exceedsDailyCap();
    }
}
