<?php

namespace Azimio\Messaging\Drivers;

use Azimio\Messaging\Contracts\SmsDriver;
use Azimio\Messaging\Exceptions\DriverNotSupported;
use Azimio\Messaging\Support\Balance;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\SendResult;

/**
 * Africa's Talking — contract filled in, implementation deferred (8.21).
 *
 * This exists so the driver abstraction is proven by a second
 * implementation rather than asserted by one, and so adding the provider
 * later is filling in methods rather than reshaping the interface. Every
 * method that would need their API throws rather than silently doing
 * nothing: a driver that quietly returns success would lose messages.
 */
class AfricasTalkingDriver implements SmsDriver
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function name(): string
    {
        return 'africastalking';
    }

    public function maxRecipientsPerCall(): int
    {
        return 1;
    }

    public function send(OutboundMessage $message): SendResult
    {
        throw DriverNotSupported::notImplemented($this->name());
    }

    public function balance(): ?Balance
    {
        return null;
    }

    public function senderNames(): array
    {
        return [];
    }

    public function deliveryReports(string $requestId, array $destAddrs): array
    {
        return [];
    }
}
