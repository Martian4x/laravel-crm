<?php

namespace Azimio\Messaging\Jobs;

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Messaging\Support\OutboundMessage;
use Azimio\Messaging\Support\Recipient;
use Azimio\Messaging\Support\Reservation;
use Azimio\Messaging\Support\SendResult;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Submits one batch to the provider and settles its credits.
 *
 * Tenant context rides along automatically: Phase 3.10 stamps every queued
 * job with the dispatching tenant and restores it before handle() runs, so
 * the scoped queries below are already correct.
 *
 * RESUMABILITY: the job always re-reads which messages are still
 * non-terminal rather than trusting a list captured at dispatch time. A
 * crashed or retried job therefore picks up where it stopped instead of
 * re-sending — which would charge and deliver twice.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Retries cover losing the worker, not provider refusals — those are
     * recorded, never retried.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $batchId,
        public readonly int $reservedCredits = 0,
    ) {
    }

    public function handle(DriverManager $drivers, CreditLedger $ledger): void
    {
        $batch = Batch::withoutTenancy()->find($this->batchId);

        if (! $batch) {
            return;
        }

        $profile = $batch->senderProfile;

        if (! $profile || ! $profile->canSend()) {
            $this->failBatch($batch, 'sender_not_approved', 'Sender name is not approved.');

            return;
        }

        // Only messages that have not reached a terminal state: this is
        // what makes a retry safe.
        $pending = $batch->messages()
            ->withoutTenancy()
            ->whereIn('status', [SmsStatus::Queued->value, SmsStatus::Sending->value])
            ->get();

        if ($pending->isEmpty()) {
            $this->closeBatch($batch);

            return;
        }

        $driver = $drivers->for($profile);
        $tenant = Tenant::withoutGlobalScopes()->find($batch->tenant_id);

        $outbound = new OutboundMessage(
            senderName: $profile->sender_name,
            body: $batch->body,
            recipients: $pending->map(fn (Message $m) => new Recipient($m->id, $m->dest_addr))->all(),
        );

        foreach ($outbound->chunk($driver->maxRecipientsPerCall()) as $chunk) {
            $ids = array_map(fn (Recipient $r) => $r->reference, $chunk->recipients);

            Message::withoutTenancy()->whereIn('id', $ids)
                ->update(['status' => SmsStatus::Sending->value]);

            $result = $driver->send($chunk);

            $this->recordChunk($batch, $ids, $result, $tenant, $ledger, $profile->isPlatform());
        }

        $this->closeBatch($batch);
    }

    /**
     * Write the provider's answer against every message in the chunk, then
     * settle or release the credits those messages were holding.
     */
    private function recordChunk(
        Batch $batch,
        array $ids,
        SendResult $result,
        ?Tenant $tenant,
        CreditLedger $ledger,
        bool $platformMode,
    ): void {
        $segments = (int) Message::withoutTenancy()->whereIn('id', $ids)->sum('segments');

        if ($result->successful) {
            Message::withoutTenancy()->whereIn('id', $ids)->update([
                'status'              => SmsStatus::Submitted->value,
                'provider_request_id' => $result->requestId,
                'submitted_at'        => now(),
                // Charged per segment (8.0.1), so copy each row's own count.
                'credits_charged'     => $platformMode ? DB::raw('segments') : 0,
            ]);

            // Accepted by the provider, so the operator has charged us:
            // the hold becomes a permanent debit (decision 8.0.2).
            if ($platformMode && $tenant) {
                $ledger->settle(
                    new Reservation($tenant->id, $segments, Batch::class, $batch->id),
                    'Batch #'.$batch->id,
                );
            }

            return;
        }

        // Refused before reaching the network. Nothing was charged, so the
        // hold is released and NO ledger row is written.
        if ($platformMode && $tenant) {
            $ledger->release(new Reservation($tenant->id, $segments, Batch::class, $batch->id));
        }

        $ownProviderFailure = ! $platformMode && $result->failedBecauseOutOfBalance();

        $shortfall = null;

        if ($ownProviderFailure && $tenant) {
            // Decision 8.0.6: the UI offers an AZIMIOCRM retry only when
            // platform credits already cover it, so compute the gap now.
            $shortfall = max(0, $segments - $ledger->balance($tenant));
        }

        Message::withoutTenancy()->whereIn('id', $ids)->update([
            'status' => $ownProviderFailure
                ? SmsStatus::FailedOwnProvider->value
                : SmsStatus::Failed->value,
            'error_code'       => $result->providerCode,
            'error_message'    => $result->providerMessage,
            'credit_shortfall' => $shortfall,
            'credits_charged'  => 0,
        ]);
    }

    private function closeBatch(Batch $batch): void
    {
        $counts = $batch->messages()->withoutTenancy()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $failed = (int) $counts->get(SmsStatus::Failed->value, 0)
            + (int) $counts->get(SmsStatus::FailedOwnProvider->value, 0)
            + (int) $counts->get(SmsStatus::Rejected->value, 0);

        $submitted = (int) $counts->get(SmsStatus::Submitted->value, 0);
        $delivered = (int) $counts->get(SmsStatus::Delivered->value, 0);

        $batch->forceFill([
            'status'          => $failed === 0 ? 'completed' : ($submitted + $delivered > 0 ? 'partially_failed' : 'completed'),
            'queued_count'    => 0,
            'submitted_count' => $submitted,
            'delivered_count' => $delivered,
            'failed_count'    => $failed,
            'credits_charged' => (int) $batch->messages()->withoutTenancy()->sum('credits_charged'),
            'completed_at'    => now(),
        ])->save();
    }

    private function failBatch(Batch $batch, string $code, string $message): void
    {
        $batch->messages()->withoutTenancy()
            ->whereIn('status', [SmsStatus::Queued->value, SmsStatus::Sending->value])
            ->update([
                'status'        => SmsStatus::Failed->value,
                'error_code'    => $code,
                'error_message' => $message,
            ]);

        $this->closeBatch($batch);
    }
}
