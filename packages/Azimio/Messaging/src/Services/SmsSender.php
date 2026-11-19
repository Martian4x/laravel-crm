<?php

namespace Azimio\Messaging\Services;

use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Jobs\SendSmsJob;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Support\SendPlan;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The one path every send takes — single or bulk, platform or own provider.
 *
 * plan() → dispatch() is the whole public surface. There is no second route
 * that skips the gates, because a "quick send" that bypassed opt-out or
 * credit checks is how tenants get billed for messages they were not
 * allowed to send.
 *
 * Order matters and is decision 8.0.2: rows are written first, credits are
 * RESERVED second, and the ledger debit is only written when the provider
 * accepts (in SendSmsJob). Never debit at enqueue and hope.
 */
class SmsSender
{
    public function __construct(
        private readonly SendPlanner $planner,
        private readonly CreditLedger $ledger,
    ) {}

    public function plan(
        Tenant $tenant,
        SenderProfile $profile,
        string $body,
        array $recipients,
        TemplateCategory $category = TemplateCategory::Transactional,
    ): SendPlan {
        return $this->planner->plan($tenant, $profile, $body, $recipients, $category);
    }

    /**
     * Persist the plan and queue it.
     *
     * Returns the batch, or null when the plan had nothing sendable or the
     * balance could not cover it — callers surface the reason from the plan
     * rather than from an exception, because a partially-bad recipient list
     * is normal rather than exceptional.
     */
    public function dispatch(
        Tenant $tenant,
        SenderProfile $profile,
        SendPlan $plan,
        ?string $name = null,
        ?int $actorId = null,
    ): ?Batch {
        // isSendable() covers recipients, credits AND the daily cap (8.12),
        // so a new guard added to the plan cannot be forgotten here.
        if (! $plan->isSendable()) {
            return null;
        }

        // The plan's body is final: it already carries the unsubscribe
        // notice for marketing, and it is the text the quoted price was
        // counted from.
        $body = $plan->body;

        $batch = DB::transaction(function () use ($tenant, $profile, $body, $plan, $name, $actorId) {
            $batch = new Batch;

            $batch->forceFill([
                'tenant_id'         => $tenant->id,
                'sender_profile_id' => $profile->id,
                'name'              => $name ?: str($body)->limit(40)->value(),
                'body'              => $body,
                'audience_type'     => 'manual',
                'total_recipients'  => $plan->sendableCount(),
                'queued_count'      => $plan->sendableCount(),
                'credits_estimated' => $plan->creditsRequired,
                'status'            => $plan->isDeferred() ? 'scheduled' : 'sending',
                'scheduled_at'      => $plan->deferredUntil,
                'created_by'        => $actorId,
            ])->save();

            foreach ($plan->sendable() as $recipient) {
                $message = new Message;

                $message->forceFill([
                    'tenant_id'         => $tenant->id,
                    'batch_id'          => $batch->id,
                    'sender_profile_id' => $profile->id,
                    'recipient_type'    => $recipient->recipientType,
                    'recipient_id'      => $recipient->recipientId,
                    'dest_addr'         => $recipient->e164,
                    'raw_number'        => $recipient->raw,
                    'body'              => $body,
                    'encoding'          => $plan->segments->encoding->value,
                    'segments'          => $plan->segments->segments,
                    'cost_source'       => $plan->mode->value,
                    'status'            => SmsStatus::Queued->value,
                    'scheduled_at'      => $plan->deferredUntil,
                ])->save();
            }

            return $batch;
        });

        // Reserved AFTER the rows exist, so a reservation can always be
        // traced to the batch holding it (and released if dispatch fails).
        $reservation = $this->ledger->reserve(
            $tenant,
            $plan->creditsRequired,
            Batch::class,
            $batch->id,
        );

        if ($reservation === null) {
            // Lost a race for the last credits between plan and dispatch.
            $batch->forceFill(['status' => 'cancelled', 'queued_count' => 0])->save();
            $batch->messages()->update([
                'status'        => SmsStatus::Failed->value,
                'error_code'    => 'insufficient_credits',
                'error_message' => 'Not enough platform credits at dispatch time.',
            ]);

            return null;
        }

        SendSmsJob::dispatch($batch->id, $reservation->credits)
            ->onQueue((string) config('messaging.batch.queue', 'default'))
            ->delay($plan->deferredUntil);

        return $batch;
    }

    /**
     * Credits a batch still has reserved, used when resuming one.
     */
    public function outstandingCredits(Batch $batch): int
    {
        if ($batch->senderProfile?->isPlatform() !== true) {
            return 0;
        }

        return (int) $batch->messages()
            ->whereIn('status', [SmsStatus::Queued->value, SmsStatus::Sending->value])
            ->sum('segments');
    }
}
