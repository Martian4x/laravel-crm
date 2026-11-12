<?php

namespace Azimio\Tenancy\Console\Commands;

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantTables;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdoptOrphanData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'azimio:tenant:adopt
        {tenant : Tenant id or slug that will own the orphan data}
        {--dry-run : Only report what would be adopted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign all orphan (NULL tenant_id) business data to a tenant — for migrating a pre-tenancy installation';

    /**
     * Strict tables that are skipped entirely, because a NULL tenant_id row
     * in them is platform-owned rather than orphaned.
     *
     * @var string[]
     */
    private const NEVER_ADOPT = [
        'sms_sender_profiles',
    ];

    public function handle(): int
    {
        $key = $this->argument('tenant');

        $tenant = is_numeric($key)
            ? Tenant::find($key)
            : Tenant::where('slug', $key)->first();

        if (! $tenant) {
            $this->error("Tenant [{$key}] not found.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $total = 0;

        // Strict business tables only: NULL rows in shared tables (pipelines,
        // roles, attributes, ...) are platform defaults and must stay NULL.
        foreach (TenantTables::STRICT as $table) {
            // Strict tables whose NULL rows are still platform-owned, so a
            // NULL tenant_id there is never an orphan. sms_sender_profiles
            // holds the platform AZIMIOCRM profile (and its encrypted
            // provider credentials) as its NULL row; adopting it would hand
            // the platform's own sending identity to one tenant.
            if (in_array($table, self::NEVER_ADOPT, true)) {
                continue;
            }

            $query = DB::table($table)->whereNull('tenant_id');

            // Platform super admins keep their NULL tenant_id.
            if ($table === 'users') {
                $query->where('is_super_admin', 0);
            }

            $count = $dryRun ? $query->count() : $query->update(['tenant_id' => $tenant->id]);

            $total += $count;

            if ($count) {
                $this->line(sprintf('%s%-20s %d rows', $dryRun ? '[dry-run] ' : '', $table, $count));
            }
        }

        $this->info(sprintf(
            '%s%d orphan rows %s tenant #%d (%s).',
            $dryRun ? '[dry-run] ' : '',
            $total,
            $dryRun ? 'would be adopted by' : 'adopted by',
            $tenant->id,
            $tenant->name
        ));

        return self::SUCCESS;
    }
}
