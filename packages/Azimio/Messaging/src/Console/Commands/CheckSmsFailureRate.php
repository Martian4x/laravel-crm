<?php

namespace Azimio\Messaging\Console\Commands;

use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Mail\SmsFailureRateAlert;
use Azimio\Messaging\Models\Message;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\User\Models\User;

/**
 * Alerts platform admins when a tenant's sends start failing (8.18).
 *
 * A failing integration is invisible otherwise: messages queue, fail, and
 * the tenant assumes SMS "does not work" without telling anyone. This
 * catches it from our side.
 *
 * A minimum volume is required before any rate is reported — one failure
 * out of two messages is 50% and means nothing, and alerting on it would
 * make the alert worthless.
 */
class CheckSmsFailureRate extends Command
{
    protected $signature = 'azimio:sms:check-failure-rate
        {--hours=6 : Window to examine}
        {--threshold=25 : Failure percentage that triggers an alert}
        {--min-volume=20 : Minimum messages before a rate is meaningful}';

    protected $description = 'Alert platform admins about tenants whose SMS failure rate is high';

    public function handle(): int
    {
        $since = now()->subHours((int) $this->option('hours'));
        $threshold = (float) $this->option('threshold');
        $minVolume = (int) $this->option('min-volume');

        $alerted = 0;

        foreach (Tenant::withoutGlobalScopes()->where('status', 1)->get() as $tenant) {
            $base = Message::withoutTenancy()
                ->where('tenant_id', $tenant->id)
                ->where('direction', 'outbound')
                ->where('created_at', '>=', $since);

            $total = (clone $base)->count();

            if ($total < $minVolume) {
                continue;
            }

            $failed = (clone $base)->whereIn('status', [
                SmsStatus::Failed->value,
                SmsStatus::FailedOwnProvider->value,
                SmsStatus::Rejected->value,
                SmsStatus::Undeliverable->value,
            ])->count();

            $rate = round($failed / $total * 100, 1);

            if ($rate < $threshold) {
                continue;
            }

            if ($this->sendAlert($tenant, $rate, $failed, $total)) {
                $alerted++;
            }
        }

        $this->info("Failure-rate check complete. Alerts sent: {$alerted}.");

        return self::SUCCESS;
    }

    /**
     * Throttled per tenant per day: a provider outage would otherwise mail
     * on every run until it was fixed.
     */
    private function sendAlert(Tenant $tenant, float $rate, int $failed, int $total): bool
    {
        $key = 'azimio:sms:failure-alert:'.$tenant->id.':'.now()->toDateString();

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addDay());

        try {
            $admins = User::withoutTenancy()
                ->where('is_super_admin', 1)
                ->where('status', 1)
                ->pluck('email')
                ->filter()
                ->all();

            if ($admins === []) {
                return false;
            }

            Mail::to($admins)->queue(new SmsFailureRateAlert($tenant, $rate, $failed, $total));

            $this->warn("{$tenant->name}: {$rate}% of {$total} messages failed.");

            return true;
        } catch (Throwable $e) {
            Log::error('[sms] failure-rate alert could not be sent', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
