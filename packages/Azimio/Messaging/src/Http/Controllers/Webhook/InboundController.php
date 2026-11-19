<?php

namespace Azimio\Messaging\Http\Controllers\Webhook;

use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Models\WebhookEvent;
use Azimio\Messaging\Support\PhoneNormalizer;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

/**
 * Inbound / two-way SMS callback (8.19).
 *
 * READ THIS BEFORE ENABLING IT. Beem documents NO signature or HMAC scheme
 * for inbound callbacks — only an unspecified "optionally validate the API
 * key". So a request arriving here CANNOT be proven to come from Beem, and
 * every payload is treated as hostile input:
 *
 *  - the raw body is logged to sms_webhook_events BEFORE anything is
 *    trusted, including rejected ones, because a run of rejections is what
 *    a forged-callback attempt looks like;
 *  - a shared secret is required when configured, compared with
 *    hash_equals, and a mismatch is recorded and refused;
 *  - processing is idempotent on transaction_id, since Beem retries;
 *  - tenant context is resolved from the sender profile that owns the
 *    receiving number, NEVER from the request body, which the caller
 *    controls.
 *
 * The route is disabled by default (messaging.webhooks.enabled = false).
 * Ask Beem for a real verification method before turning it on.
 */
class InboundController extends Controller
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly TenantManager $tenants,
    ) {}

    public function __invoke(Request $request)
    {
        $payload = $request->all();

        $valid = $this->verify($request);

        $event = new WebhookEvent;

        $event->forceFill([
            'provider'        => 'beem',
            'event_type'      => 'inbound',
            'payload'         => $payload,
            'signature_valid' => $valid,
        ])->save();

        if (! $valid) {
            Log::warning('[sms] inbound callback rejected', ['event_id' => $event->id]);

            return response()->json(['successful' => false], 403);
        }

        $transactionId = (string) ($payload['transaction_id'] ?? '');

        // Beem retries, so a repeat must not create a second message or
        // re-run the opt-out.
        if ($transactionId !== '' && $this->alreadyProcessed($transactionId, $event->id)) {
            return $this->acknowledge($transactionId);
        }

        $this->record($payload, $event);

        $event->forceFill(['processed_at' => now()])->save();

        // Beem expects this exact shape back.
        return $this->acknowledge($transactionId);
    }

    /**
     * A shared secret, if one is configured. Not a signature — see the
     * class docblock for why that is the best available.
     */
    private function verify(Request $request): bool
    {
        $secret = config('messaging.webhooks.shared_secret');

        if (! $secret) {
            return true;
        }

        $presented = (string) ($request->header('X-Azimio-Secret') ?? $request->input('secret', ''));

        return $presented !== '' && hash_equals((string) $secret, $presented);
    }

    private function alreadyProcessed(string $transactionId, int $exceptEventId): bool
    {
        return WebhookEvent::query()
            ->where('event_type', 'inbound')
            ->whereNotNull('processed_at')
            ->where('id', '!=', $exceptEventId)
            ->where('payload->transaction_id', $transactionId)
            ->exists();
    }

    /**
     * Log the message and honour a STOP reply.
     */
    private function record(array $payload, WebhookEvent $event): void
    {
        $from = $this->phones->normalize((string) ($payload['from'] ?? ''));
        $to = preg_replace('/\D+/', '', (string) ($payload['to'] ?? ''));
        $text = (string) ($payload['message']['text'] ?? '');

        if (! $from->isValid()) {
            return;
        }

        // Tenant comes from the profile that owns the receiving number, not
        // from the body.
        $profile = $this->profileForNumber($to);

        if (! $profile || ! $profile->tenant_id) {
            Log::warning('[sms] inbound callback for an unknown number', [
                'event_id' => $event->id,
            ]);

            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($profile->tenant_id);

        if (! $tenant) {
            return;
        }

        $this->tenants->runAs($tenant, function () use ($from, $text, $profile, $event) {
            $person = $this->personFor($from->e164);

            $message = new Message;

            $message->forceFill([
                'tenant_id'         => $profile->tenant_id,
                'sender_profile_id' => $profile->id,
                'dest_addr'         => $from->e164,
                'raw_number'        => $from->raw,
                'body'              => $text,
                'direction'         => 'inbound',
                'status'            => SmsStatus::Delivered->value,
                'credits_charged'   => 0,
                'recipient_type'    => $person ? Person::class : null,
                'recipient_id'      => $person?->id,
                'delivered_at'      => now(),
            ])->save();

            $event->forceFill(['message_id' => $message->id])->save();

            if ($this->isStopKeyword($text)) {
                OptOut::firstOrCreate(
                    ['dest_addr' => $from->e164],
                    ['source' => 'stop_reply', 'person_id' => $person?->id],
                );
            }
        });
    }

    /**
     * STOP in English, ACHA in Swahili — both markets this platform serves.
     */
    private function isStopKeyword(string $text): bool
    {
        $word = strtoupper(trim($text));

        return in_array($word, ['STOP', 'ACHA', 'SITAKI', 'UNSUBSCRIBE'], true);
    }

    private function profileForNumber(string $digits): ?SenderProfile
    {
        if ($digits === '') {
            return null;
        }

        return SenderProfile::withoutTenancy()
            ->whereNotNull('tenant_id')
            ->where('sender_name', $digits)
            ->first();
    }

    private function personFor(string $e164): ?Person
    {
        $tail = substr(preg_replace('/\D+/', '', $e164), -9);

        return Person::query()
            ->where('contact_numbers', 'like', '%'.$tail.'%')
            ->first();
    }

    private function acknowledge(string $transactionId)
    {
        return response()->json([
            'transaction_id' => $transactionId,
            'successful'     => true,
        ]);
    }
}
