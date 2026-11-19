<?php

namespace Azimio\Messaging\Console\Commands;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Mail\LowSmsBalance;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\User\Models\User;

/**
 * Warns tenants whose platform credits are running out (completes 8.13).
 *
 * Only platform-mode tenants are relevant: an own-provider tenant's balance
 * lives at their provider and is none of our business.
 *
 * Deliberately throttled with a cache key per tenant. A daily scheduled run
 * would otherwise mail the same tenant every day until they topped up,
 * which trains people to ignore the warning.
 */
class NotifyLowSmsBalance extends Command
{
    protected $signature = 'azimio:sms:notify-low-balance
        {--force : Ignore the per-tenant throttle}';

    protected $description = 'Email tenants whose SMS credit balance has fallen below their threshold';

    public function handle(): int
    {
        $tenants = Tenant::withoutGlobalScopes()
            ->where('status', 1)
            ->where('sms_mode', SmsMode::Platform->value)
            ->get();

        $notified = 0;

        foreach ($tenants as $tenant) {
            $threshold = (int) ($tenant->sms_low_credit_threshold
                ?: config('messaging.credits.low_balance_threshold', 50));

            if ((int) $tenant->sms_credits > $threshold) {
                continue;
            }

            if (! $this->option('force') && ! $this->shouldNotify($tenant)) {
                continue;
            }

            if ($this->notify($tenant, $threshold)) {
                $notified++;
            }
        }

        $this->info("Checked {$tenants->count()} tenant(s), notified {$notified}.");

        return self::SUCCESS;
    }

    /**
     * One warning per tenant per week, so a long-running low balance does
     * not become noise people filter out.
     */
    private function shouldNotify(Tenant $tenant): bool
    {
        $key = 'azimio:sms:low-balance-notified:'.$tenant->id;

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, now()->addWeek());

        return true;
    }

    private function notify(Tenant $tenant, int $threshold): bool
    {
        try {
            $emails = User::withoutTenancy()
                ->where('tenant_id', $tenant->id)
                ->where('status', 1)
                ->pluck('email')
                ->filter()
                ->all();

            if ($emails === []) {
                return false;
            }

            Mail::to($emails)->queue(new LowSmsBalance($tenant, $threshold));

            return true;
        } catch (Throwable $e) {
            Log::error('[sms] low-balance notification failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
