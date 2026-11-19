<?php

namespace Azimio\Tenancy\Console\Commands;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Suspend tenants whose trial has lapsed (7.5).
 *
 * Suspends rather than deletes, and records the reason, so the data is
 * intact if they pay and the next admin can see why they are locked out.
 * A tenant with no trial_ends_at is on a real plan and is never touched.
 */
class ExpireTrials extends Command
{
    protected $signature = 'azimio:tenant:expire-trials
        {--dry-run : Report what would be suspended without changing anything}';

    protected $description = 'Suspend tenants whose trial period has ended';

    public function handle(): int
    {
        $expired = Tenant::withoutGlobalScopes()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired trials.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($expired as $tenant) {
            $this->line(sprintf(
                '%s%s — trial ended %s',
                $dryRun ? '[dry-run] ' : '',
                $tenant->name,
                $tenant->trial_ends_at->toDateString(),
            ));

            if ($dryRun) {
                continue;
            }

            $tenant->forceFill([
                'status'            => Tenant::STATUS_SUSPENDED,
                'suspended_at'      => now(),
                'suspension_reason' => trans('azimio::app.tenants.trial-expired-reason', [
                    'date' => $tenant->trial_ends_at->toDateString(),
                ]),
            ])->save();
        }

        $this->info(sprintf(
            '%s%d trial(s) %s.',
            $dryRun ? '[dry-run] ' : '',
            $expired->count(),
            $dryRun ? 'would be suspended' : 'suspended',
        ));

        return self::SUCCESS;
    }
}
