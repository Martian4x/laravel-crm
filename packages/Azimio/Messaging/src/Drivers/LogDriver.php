<?php

namespace Azimio\Messaging\Drivers;

use Azimio\Messaging\Contracts\SmsDriver;
use Azimio\Messaging\Support\Balance;
use Azimio\Messaging\Support\DeliveryReport;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to the log and contacts nothing.
 *
 * The default in development and the test suite: it exercises the whole
 * pipeline — reservation, submission, settlement, delivery status — without
 * a network call, an account, or a real charge. It reports every message as
 * delivered, so batch completion paths are reachable in tests.
 *
 * It deliberately logs the body: in local development that is the point.
 * It is never a production driver.
 */
class LogDriver implements SmsDriver
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function send(OutboundMessage $message): SendResult
    {
        $requestId = (string) Str::uuid();

        Log::channel($this->config['channel'] ?? config('logging.default'))->info('[sms] outbound', [
            'driver'      => $this->name(),
            'request_id'  => $requestId,
            'sender_name' => $message->senderName,
            'recipients'  => array_map(fn ($r) => $r->destAddr, $message->recipients),
            'encoding'    => $message->encoding->value,
            'scheduled'   => $message->scheduledAt?->format('Y-m-d H:i'),
            'body'        => $message->body,
        ]);

        return SendResult::accepted(
            requestId: $requestId,
            accepted: $message->recipientCount(),
            raw: ['driver' => 'log'],
        );
    }

    /**
     * A fixed, obviously-fake figure. Returning null would make the balance
     * UI untestable; returning something plausible-looking would invite
     * someone to read it as real.
     */
    public function balance(): ?Balance
    {
        return new Balance(999999.0, 'credits', ['driver' => 'log']);
    }

    public function senderNames(): array
    {
        return [[
            'sender_name' => config('messaging.platform.sender_name'),
            'status'      => 'active',
        ]];
    }

    public function deliveryReports(string $requestId, array $destAddrs): array
    {
        return array_map(
            fn (string $destAddr) => new DeliveryReport(
                destAddr: $destAddr,
                status: SmsStatus::Delivered,
                requestId: $requestId,
                raw: ['driver' => 'log'],
            ),
            $destAddrs
        );
    }

    public function maxRecipientsPerCall(): int
    {
        return 1000;
    }

    public function name(): string
    {
        return 'log';
    }
}
