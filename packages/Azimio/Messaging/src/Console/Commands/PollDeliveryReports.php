<?php

namespace Azimio\Messaging\Console\Commands;

use Azimio\Messaging\DriverManager;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Fetch delivery statuses for submitted messages (8.19).
 *
 * This is a COMMAND, not a webhook, and that is a correction to the
 * original plan: Beem's REST SMS API has no delivery callback at all.
 * Status is polled with (request_id, dest_addr), one request per recipient,
 * and their docs ask for a five-minute wait after submitting.
 *
 * The request cost is the thing to respect here. A 10,000-recipient batch
 * is 10,000 polls, so:
 *
 *  - only messages in a non-terminal submitted state are polled;
 *  - nothing younger than the driver's minimum age is touched;
 *  - anything older than delivery_reports.max_age_hours is given up on and
 *    left as submitted, because polling it forever costs requests and will
 *    never resolve;
 *  - --limit caps the work per run so a scheduled invocation cannot run
 *    away.
 */
class PollDeliveryReports extends Command
{
    protected $signature = 'azimio:sms:poll-delivery-reports
        {--limit=500 : Maximum messages to poll in this run}
        {--tenant= : Restrict to one tenant id}';

    protected $description = 'Poll the SMS provider for delivery statuses of submitted messages';

    public function handle(DriverManager $drivers): int
    {
        if (! config('messaging.delivery_reports.enabled', true)) {
            $this->info('Delivery-report polling is disabled.');

            return self::SUCCESS;
        }

        $maxAgeHours = (int) config('messaging.delivery_reports.max_age_hours', 48);

        $pending = Message::withoutTenancy()
            ->where('status', SmsStatus::Submitted->value)
            ->whereNotNull('provider_request_id')
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', (int) $this->option('tenant')))
            ->where('submitted_at', '<=', now()->subMinutes($this->minimumAgeMinutes()))
            // Given up on: still unresolved after the window, so stop
            // spending requests on it.
            ->where('submitted_at', '>=', now()->subHours($maxAgeHours))
            ->orderBy('submitted_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to poll.');

            return self::SUCCESS;
        }

        $updated = 0;

        // Grouped so one driver instance serves a whole submission rather
        // than being rebuilt per message.
        foreach ($pending->groupBy(['sender_profile_id', 'provider_request_id']) as $profileId => $byRequest) {
            $profile = SenderProfile::withoutTenancy()->find($profileId);

            if (! $profile) {
                continue;
            }

            try {
                $driver = $drivers->for($profile);
            } catch (Throwable $e) {
                $this->warn("Driver for profile {$profileId} unavailable: {$e->getMessage()}");

                continue;
            }

            foreach ($byRequest as $requestId => $messages) {
                $updated += $this->pollRequest($driver, (string) $requestId, $messages);
            }
        }

        $this->info("Polled {$pending->count()} message(s), updated {$updated}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function pollRequest($driver, string $requestId, $messages): int
    {
        try {
            $reports = $driver->deliveryReports($requestId, $messages->pluck('dest_addr')->all());
        } catch (Throwable $e) {
            $this->warn("Request {$requestId} failed: {$e->getMessage()}");

            return 0;
        }

        $updated = 0;

        foreach ($reports as $report) {
            // PENDING maps to Submitted, which is not terminal — leave the
            // row alone and let the next run pick it up.
            if ($report->isPending()) {
                continue;
            }

            $message = $messages->firstWhere('dest_addr', $report->destAddr);

            if (! $message) {
                continue;
            }

            $message->forceFill([
                'status'       => $report->status->value,
                'delivered_at' => $report->status === SmsStatus::Delivered ? now() : null,
            ])->save();

            $updated++;
        }

        if ($updated > 0) {
            $this->refreshBatchCounts($messages->pluck('batch_id')->filter()->unique());
        }

        return $updated;
    }

    /**
     * Keep the denormalised batch counters honest after a status change.
     *
     * @param  Collection<int, int>  $batchIds
     */
    private function refreshBatchCounts($batchIds): void
    {
        foreach ($batchIds as $batchId) {
            $batch = Batch::withoutTenancy()->find($batchId);

            if (! $batch) {
                continue;
            }

            $counts = $batch->messages()->withoutTenancy()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $batch->forceFill([
                'delivered_count' => (int) $counts->get(SmsStatus::Delivered->value, 0),
                'submitted_count' => (int) $counts->get(SmsStatus::Submitted->value, 0),
                'failed_count'    => (int) $counts->get(SmsStatus::Failed->value, 0)
                    + (int) $counts->get(SmsStatus::FailedOwnProvider->value, 0)
                    + (int) $counts->get(SmsStatus::Rejected->value, 0)
                    + (int) $counts->get(SmsStatus::Undeliverable->value, 0),
            ])->save();
        }
    }

    /**
     * Beem asks for at least five minutes after submitting.
     */
    private function minimumAgeMinutes(): int
    {
        return (int) config('messaging.drivers.beem.dlr_min_age_minutes', 5);
    }
}
