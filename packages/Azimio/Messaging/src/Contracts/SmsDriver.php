<?php

namespace Azimio\Messaging\Contracts;

use Azimio\Messaging\Support\Balance;
use Azimio\Messaging\Support\DeliveryReport;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\SendResult;

/**
 * What every SMS provider must offer the sending pipeline.
 *
 * Implementations are constructed with one sender profile's credentials and
 * are therefore single-tenant by nature — resolve one per send via
 * DriverManager rather than holding a shared instance.
 *
 * Contract rules every driver must honour:
 *
 *  - send() NEVER throws for a provider-side refusal. A refused submission
 *    is a SendResult::failed() with a mapped SendFailure, because the
 *    pipeline has to record it against the message row and release the
 *    credit reservation (decision 8.0.2). Throwing is reserved for
 *    programmer error, such as missing credentials.
 *  - send() accepts 1..N recipients and returns ONE result for the whole
 *    submission. Chunking to the provider's per-call limit is the caller's
 *    job, via OutboundMessage::chunk().
 *  - balance() and senderNames() return null / an empty array when the
 *    provider does not expose them, rather than throwing.
 */
interface SmsDriver
{
    /**
     * Submit a message to one or more recipients.
     */
    public function send(OutboundMessage $message): SendResult;

    /**
     * Remaining balance on the sending account, or null if unsupported.
     */
    public function balance(): ?Balance;

    /**
     * Sender names registered on the account, as arrays with at least
     * 'sender_name' and 'status' keys. Empty when unsupported.
     *
     * @return array<int, array<string, mixed>>
     */
    public function senderNames(): array;

    /**
     * Delivery statuses for a previous submission.
     *
     * Modelled as polling because that is what Beem offers — their REST SMS
     * API has no delivery callback. A provider that pushes instead may
     * return an empty array here and update rows from its webhook.
     *
     * @param  string[]  $destAddrs
     * @return DeliveryReport[]
     */
    public function deliveryReports(string $requestId, array $destAddrs): array;

    /**
     * How many recipients this provider accepts in a single send() call.
     */
    public function maxRecipientsPerCall(): int;

    /**
     * Driver key, matching the sms_sender_profiles.driver column.
     */
    public function name(): string;
}
