<?php

namespace Azimio\Tenancy;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Permanent deletion of a tenant (7.13).
 *
 * The order is suspend → export → delete, and it is enforced rather than
 * suggested:
 *
 *  - a tenant that is still active cannot be deleted, because deleting an
 *    account someone is actively using is almost always a mistake;
 *  - deletion is blocked until the grace period has passed, so there is a
 *    window in which a wrong click can be undone by reactivating;
 *  - the caller is expected to have taken an export first (7.12), which the
 *    UI makes the obvious path.
 *
 * FK cascades already remove child rows, so this class is mostly about the
 * things cascades cannot reach: uploaded files, and a record of what was
 * destroyed.
 */
class TenantEraser
{
    public function graceDays(): int
    {
        return (int) config('azimio.deletion_grace_days', 14);
    }

    /**
     * Why this tenant may not be deleted yet, or null when it may.
     *
     * Returns a reason rather than a bool so the UI can say which rule is
     * in the way instead of just greying a button out.
     */
    public function blockedReason(Tenant $tenant): ?string
    {
        if ($tenant->isActive()) {
            return trans('azimio::app.tenants.delete-blocked-active');
        }

        $remaining = $this->daysRemaining($tenant);

        if ($remaining > 0) {
            return trans('azimio::app.tenants.delete-blocked-grace', [
                'days' => $remaining,
                'date' => $this->eligibleAt($tenant)->toDateString(),
            ]);
        }

        return null;
    }

    public function canDelete(Tenant $tenant): bool
    {
        return $this->blockedReason($tenant) === null;
    }

    public function daysRemaining(Tenant $tenant): int
    {
        if ($tenant->isActive()) {
            return $this->graceDays();
        }

        // Rounded UP: a partial day still counts as a day of grace, and
        // truncating cost a whole day to sub-second drift.
        return max(0, (int) ceil(now()->floatDiffInDays($this->eligibleAt($tenant), false)));
    }

    /**
     * Grace runs from when they were suspended; a tenant suspended before
     * `suspended_at` existed falls back to its last update.
     */
    public function eligibleAt(Tenant $tenant): \Carbon\CarbonInterface
    {
        $from = $tenant->suspended_at ?? $tenant->updated_at ?? $tenant->created_at;

        return $from->copy()->addDays($this->graceDays());
    }

    /**
     * Destroy the tenant and everything belonging to it.
     *
     * @return array<string, int> rows removed per table, for the audit line
     */
    public function erase(Tenant $tenant): array
    {
        $removed = [];

        // Counted BEFORE deleting: afterwards there is nothing to count,
        // and "we deleted something" is not an audit record.
        foreach (TenantTables::STRICT as $table) {
            $removed[$table] = DB::table($table)->where('tenant_id', $tenant->id)->count();
        }

        $this->deleteFiles($tenant);

        // FK cascades clear the child rows; this is the row they hang from.
        $tenant->delete();

        Log::warning('[tenancy] tenant permanently deleted', [
            'tenant_id' => $tenant->id,
            'name'      => $tenant->name,
            'by'        => auth()->id(),
            'rows'      => array_filter($removed),
        ]);

        return array_filter($removed);
    }

    /**
     * Uploaded files, which no foreign key reaches.
     *
     * Phase 3.9 noted that upload paths are entity-id scoped rather than
     * tenant-scoped, so there is no single directory to drop. Files are
     * therefore found through the rows that reference them, before those
     * rows disappear.
     */
    private function deleteFiles(Tenant $tenant): void
    {
        $paths = [];

        foreach (['logo_path'] as $column) {
            if ($tenant->{$column}) {
                $paths[] = $tenant->{$column};
            }
        }

        // Activity and quote attachments live behind their own tables.
        foreach (['activities' => 'file_path', 'quotes' => 'file_path'] as $table => $column) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                continue;
            }

            $paths = array_merge($paths, DB::table($table)
                ->where('tenant_id', $tenant->id)
                ->whereNotNull($column)
                ->pluck($column)
                ->all());
        }

        foreach (array_filter(array_unique($paths)) as $path) {
            try {
                Storage::delete($path);
            } catch (\Throwable $e) {
                // A missing or unreadable file must not abort the deletion:
                // the database rows are the thing that matters, and an
                // orphan file is a smaller problem than a half-deleted
                // tenant.
                Log::info('[tenancy] could not delete tenant file', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
