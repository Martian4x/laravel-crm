<?php

namespace Azimio\Messaging\Drivers;

use Azimio\Messaging\Contracts\SmsDriver;
use Azimio\Messaging\Enums\SendFailure;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Exceptions\DriverNotConfigured;
use Azimio\Messaging\Support\Balance;
use Azimio\Messaging\Support\DeliveryReport;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\SendResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Beem Africa, verified against docs.beem.africa (read 2026-11-12).
 *
 * Confirmed from their OpenAPI spec and guides:
 *   POST https://apisms.beem.africa/v1/send
 *   GET  https://apisms.beem.africa/public/v1/vendors/balance
 *   GET  https://apisms.beem.africa/public/v1/sender-names
 *   GET  https://dlrapi.beem.africa/public/v1/delivery-reports
 *   Auth: HTTP Basic, api key as username, secret as password.
 *
 * Things their docs make clear that shape this class:
 *
 *  - dest_addr takes NO leading '+' and no leading zero: "255700000001".
 *  - encoding is an INTEGER, 0 for default GSM. Our Encoding enum is a
 *    string internally, so it is translated on the way out.
 *  - schedule_time is "yyyy-mm-dd hh:mm" in GMT+0, or "" to send now.
 *  - A send returns ONE request_id for the whole call and no per-recipient
 *    message id, so delivery status is looked up by (request_id, dest_addr).
 *  - request_id comes back as a string in the schema but an integer in their
 *    own example, so it is cast to string either way.
 *  - Max 1000 recipients per request (their error code 114).
 *
 * NOT documented, and deliberately not guessed at here: how Beem counts
 * segments or charges for long and Unicode messages on /v1/send. Our own
 * GSM-7 160/153 figures come from their Multicountry product, which is a
 * different API. Credit charges must be reconciled against real Beem
 * invoices before anyone relies on them for billing (see 8.0.1).
 */
class BeemDriver implements SmsDriver
{
    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'beem';
    }

    public function maxRecipientsPerCall(): int
    {
        return min(1000, (int) ($this->config['recipients_per_call'] ?? 1000));
    }

    public function send(OutboundMessage $message): SendResult
    {
        if ($message->recipientCount() > $this->maxRecipientsPerCall()) {
            return SendResult::failed(
                SendFailure::TooManyRecipients,
                providerMessage: 'Recipient count exceeds the per-request limit.',
            );
        }

        $payload = [
            'source_addr'   => $message->senderName,
            'encoding'      => $this->encodingValue($message),
            'message'       => $message->body,
            'schedule_time' => $message->scheduledAt
                ? $message->scheduledAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i')
                : '',
            'recipients'    => array_map(fn ($r) => [
                'recipient_id' => $r->reference,
                'dest_addr'    => $r->msisdn(),
            ], $message->recipients),
        ];

        if ($message->jobName) {
            $payload['job_name'] = $message->jobName;
        }

        // Built BEFORE the try: missing credentials are programmer error and
        // must propagate, not be swallowed into a "provider refused" result
        // that would look like a normal failed send.
        $client = $this->client();
        $url = $this->endpoint('send');

        try {
            $response = $client->post($url, $payload);
        } catch (Throwable $e) {
            // A transport failure on a POST is ambiguous: Beem documents no
            // idempotency key, so we cannot tell a dropped request from a
            // dropped response. Never auto-retry here — a retry risks
            // charging and delivering the same message twice. The pipeline
            // marks it transient and a human decides.
            Log::warning('[sms] beem send transport failure', ['error' => $e->getMessage()]);

            return SendResult::failed(
                SendFailure::ProviderError,
                providerMessage: 'Could not reach the provider: '.$e->getMessage(),
            );
        }

        $body = (array) $response->json();
        $code = isset($body['code']) ? (int) $body['code'] : null;

        // Beem signals success with body code 100, not merely HTTP 200.
        if ($response->successful() && $code === 100) {
            return SendResult::accepted(
                requestId: isset($body['request_id']) ? (string) $body['request_id'] : null,
                accepted: (int) ($body['valid'] ?? $message->recipientCount()),
                rejected: (int) ($body['invalid'] ?? 0),
                duplicates: (int) ($body['duplicates'] ?? 0),
                optedOut: (int) ($body['opted_out'] ?? 0),
                raw: $body,
            );
        }

        return SendResult::failed(
            failure: $this->mapFailure($code, $response->status()),
            providerCode: $code !== null ? (string) $code : (string) $response->status(),
            providerMessage: $body['message'] ?? $body['error'] ?? 'Unknown provider error.',
            raw: $body,
        );
    }

    public function balance(): ?Balance
    {
        try {
            $response = $this->client()->get($this->endpoint('balance'));
        } catch (Throwable $e) {
            Log::warning('[sms] beem balance failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = (array) $response->json();

        if (! isset($body['data']['credit_balance'])) {
            return null;
        }

        return new Balance(
            amount: (float) $body['data']['credit_balance'],
            unit: 'credits',
            raw: $body,
        );
    }

    public function senderNames(): array
    {
        try {
            $response = $this->client()->get($this->endpoint('sender_names'));
        } catch (Throwable $e) {
            Log::warning('[sms] beem sender names failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return array_map(fn (array $row) => [
            // Beem calls it 'senderid'; normalise to our own vocabulary.
            'sender_name' => $row['senderid'] ?? null,
            'status'      => $row['status'] ?? null,
            'provider_id' => $row['id'] ?? null,
            'created'     => $row['created'] ?? null,
        ], (array) ($response->json()['data'] ?? []));
    }

    /**
     * Beem's delivery-report endpoint takes ONE dest_addr per call, so a
     * batch costs one request per recipient. Their docs also ask callers to
     * wait at least five minutes after submitting before checking.
     */
    public function deliveryReports(string $requestId, array $destAddrs): array
    {
        $reports = [];

        foreach ($destAddrs as $destAddr) {
            $normalised = ltrim(preg_replace('/\D+/', '', $destAddr), '0');

            try {
                $response = $this->client()->get($this->endpoint('delivery_reports'), [
                    'dest_addr'  => $normalised,
                    'request_id' => $requestId,
                ]);
            } catch (Throwable $e) {
                Log::warning('[sms] beem dlr failed', ['error' => $e->getMessage()]);

                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            foreach ((array) $response->json() as $row) {
                if (! is_array($row) || ! isset($row['status'])) {
                    continue;
                }

                $reports[] = new DeliveryReport(
                    destAddr: $destAddr,
                    status: $this->mapDeliveryStatus((string) $row['status']),
                    requestId: isset($row['request_id']) ? (string) $row['request_id'] : $requestId,
                    raw: $row,
                );
            }
        }

        return $reports;
    }

    /**
     * Beem documents exactly three states.
     * PENDING is NOT terminal — the poller must come back for it rather
     * than writing a final status.
     */
    private function mapDeliveryStatus(string $status): SmsStatus
    {
        return match (strtoupper($status)) {
            'DELIVERED'   => SmsStatus::Delivered,
            'UNDELIVERED' => SmsStatus::Undeliverable,
            'PENDING'     => SmsStatus::Submitted,
            default       => SmsStatus::Submitted,
        };
    }

    /**
     * Beem's documented body codes, mapped to our provider-agnostic reasons.
     * 103 is the only one worth retrying; an invalid number or an
     * unregistered sender will fail identically forever.
     */
    private function mapFailure(?int $code, int $httpStatus): SendFailure
    {
        return match ($code) {
            101, 115      => SendFailure::InvalidNumber,
            102           => SendFailure::InsufficientBalance,
            109, 110, 123 => SendFailure::InvalidContent,
            111, 121, 122 => SendFailure::InvalidSender,
            112, 117      => SendFailure::InvalidSchedule,
            114           => SendFailure::TooManyRecipients,
            120           => SendFailure::AuthenticationFailed,
            default       => $httpStatus === 401
                ? SendFailure::AuthenticationFailed
                : SendFailure::ProviderError,
        };
    }

    /**
     * Beem's encoding field is an integer; 0 is their default GSM encoding
     * and the only value their /v1/send docs list. Unicode bodies are sent
     * with the same value until Beem documents another one.
     */
    private function encodingValue(OutboundMessage $message): int
    {
        return (int) ($this->config['encoding'] ?? 0);
    }

    private function endpoint(string $key): string
    {
        $url = $this->config['endpoints'][$key] ?? null;

        if (! $url) {
            throw DriverNotConfigured::missingCredentials($this->name(), "endpoints.{$key}");
        }

        return $url;
    }

    /**
     * Full URLs are passed per call rather than via baseUrl(): baseUrl with
     * an empty path appends a trailing slash, turning the documented
     * /v1/send into /v1/send/.
     */
    private function client(): PendingRequest
    {
        $apiKey = $this->config['api_key'] ?? null;
        $secret = $this->config['secret_key'] ?? null;

        if (! $apiKey || ! $secret) {
            throw DriverNotConfigured::missingCredentials($this->name(), 'api_key/secret_key');
        }

        return Http::withBasicAuth($apiKey, $secret)
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 15));
    }
}
