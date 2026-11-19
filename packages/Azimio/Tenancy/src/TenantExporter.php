<?php

namespace Azimio\Tenancy;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Everything one tenant owns, as a zip of CSVs (7.12).
 *
 * Exists for two promises: an offboarding request, and the data-residency
 * statement in DEPLOYMENT.md. Both mean "we can hand you your data", which
 * is only true if someone has actually built it.
 *
 * Deliberately raw `DB::table()` with an explicit `tenant_id` filter rather
 * than the models: an export must include rows regardless of global scopes,
 * soft deletes or whatever a model happens to hide, and it runs as a super
 * admin with no tenant context for the scope to use anyway. The filter is
 * therefore written out every time, never inferred.
 */
class TenantExporter
{
    /**
     * Tables worth handing over, in the order a human would read them.
     *
     * Not every strict table: `core_config` is settings rather than data,
     * `sms_sender_profiles` holds encrypted credentials that must never
     * leave the system, and the sms_* operational tables are noise in an
     * offboarding pack. Kept explicit so adding a table is a decision.
     *
     * @var string[]
     */
    public const TABLES = [
        'persons',
        'organizations',
        'leads',
        'quotes',
        'activities',
        'products',
        'emails',
        'tags',
        'users',
        'sms_messages',
        'sms_opt_outs',
    ];

    /**
     * Columns never exported, whatever table they appear in.
     *
     * @var string[]
     */
    private const REDACT = [
        'password',
        'remember_token',
        'api_token',
        'credentials',
    ];

    /**
     * Build the archive and return its path. The caller streams and deletes
     * it — it is deliberately not left lying in storage, since it is a full
     * copy of one customer's data.
     */
    public function export(Tenant $tenant): string
    {
        $path = storage_path('app/tenant-export-'.$tenant->id.'-'.now()->format('Ymd-His').'.zip');

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $zip->addFromString('README.txt', $this->readme($tenant));

        foreach (self::TABLES as $table) {
            $csv = $this->tableToCsv($table, $tenant->id);

            if ($csv !== null) {
                $zip->addFromString($table.'.csv', $csv);
            }
        }

        $zip->close();

        return $path;
    }

    /**
     * One table as CSV, or null when it holds nothing for this tenant —
     * an archive full of empty files is harder to read, not more complete.
     */
    private function tableToCsv(string $table, int $tenantId): ?string
    {
        $rows = DB::table($table)->where('tenant_id', $tenantId)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $columns = array_values(array_diff(
            array_keys((array) $rows->first()),
            self::REDACT,
        ));

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            $record = (array) $row;

            fputcsv($handle, array_map(
                fn (string $column) => $this->flatten($record[$column] ?? null),
                $columns,
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * JSON columns (person emails, contact numbers, activity payloads) are
     * left as their raw JSON rather than exploded: a spreadsheet can read
     * them, and inventing a column layout per table would lose data.
     */
    private function flatten(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function readme(Tenant $tenant): string
    {
        $lines = [
            'AzimioCustomers data export',
            '',
            'Company: '.$tenant->name,
            'Exported: '.now()->toDayDateTimeString(),
            '',
            'One CSV per table, UTF-8, comma separated, first row is the header.',
            'Tables with no data for this company are omitted.',
            '',
            'Columns holding structured data (for example a contact\'s emails or',
            'phone numbers) contain JSON, which spreadsheets will show as text.',
            '',
            'Passwords, API tokens and provider credentials are never exported.',
            '',
            'Tables included:',
        ];

        foreach (self::TABLES as $table) {
            $lines[] = '  - '.$table;
        }

        return implode("\n", $lines)."\n";
    }
}
