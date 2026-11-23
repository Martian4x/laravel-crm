<?php

namespace Azimio\Tenancy\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Every attribute AzimioCustomers adds on top of Krayin's base set.
 *
 * WHY THIS IS A SEEDER AND NOT A MIGRATION
 * ----------------------------------------
 * It was a migration, and that silently did not work.
 *
 * `krayin-crm:install` runs `migrate:fresh` (Installer.php:70) and THEN the
 * seeders (line 73). The very first thing AttributeSeeder does is
 * `DB::table('attributes')->delete()` (AttributeSeeder.php:19). So anything a
 * migration inserted into `attributes` is deleted moments later, while the
 * migration stays recorded as run and nothing reports a problem.
 *
 * That is how 5.4 (mobile money on quotes) and 5.5 (TRA TIN/VRN) came to be
 * ticked off while never once existing on an installed system — confirmed on
 * production and locally: migration present, attributes absent, exactly
 * Krayin's 42 base rows.
 *
 * Running after the seeder is the only point at which these rows survive, so
 * this is hooked to the `krayin.installed` event. It is also called from a
 * migration, which is what repairs databases that already exist.
 *
 * Therefore: IDEMPOTENT, always. It must be safe to run on a fresh database,
 * on a database that already has these rows, and twice in a row.
 *
 * `attributes` is SHARED (TenantTables::SHARED), so these go in with a NULL
 * tenant_id and every tenant sees them.
 */
class AzimioAttributeSeeder extends Seeder
{
    /**
     * Attribute definitions, keyed by nothing in particular — the unique key
     * that makes this idempotent is (code, entity_type).
     *
     * Types come from Krayin's fixed set: text, textarea, price, select,
     * date, lookup, email, phone, address. `phone` and `email` are
     * MULTI-VALUE (stored as json_value) and render each entry as a tel:/
     * mailto: link, which is what gives click-to-call (5.1) and the
     * WhatsApp deep link (5.2) for free.
     */
    public function definitions(): array
    {
        return [
            // ---------------------------------------------------------------
            // Organizations — the company profile.
            //
            // Before this, the table held name, address and user_id and
            // nothing else: a company record with no phone number and no
            // email address.
            // ---------------------------------------------------------------
            [
                'code'        => 'phone',
                'name'        => 'Phone Numbers',
                'type'        => 'phone',
                'entity_type' => 'organizations',
                'sort_order'  => 3,
            ],
            [
                'code'        => 'email',
                'name'        => 'Emails',
                'type'        => 'email',
                'entity_type' => 'organizations',
                'sort_order'  => 4,
            ],
            [
                'code'        => 'tin',
                'name'        => 'TIN',
                'type'        => 'text',
                'entity_type' => 'organizations',
                'sort_order'  => 5,
            ],
            [
                'code'        => 'vrn',
                'name'        => 'VRN',
                'type'        => 'text',
                'entity_type' => 'organizations',
                'sort_order'  => 6,
            ],
            [
                'code'        => 'business_type',
                'name'        => 'Type of Business',
                'type'        => 'select',
                'entity_type' => 'organizations',
                'sort_order'  => 7,
                'options'     => [
                    'Sole Proprietorship',
                    'Partnership',
                    'Limited Company',
                    'Cooperative (AMCOS)',
                    'NGO / Non-Profit',
                    'Government',
                    'Parastatal',
                    'Other',
                ],
            ],
            [
                'code'        => 'location',
                'name'        => 'Location',
                'type'        => 'text',
                'entity_type' => 'organizations',
                'sort_order'  => 8,
            ],

            // ---------------------------------------------------------------
            // Quotes — 5.4, mobile money. Mobile money is how most business
            // in Tanzania is actually settled, so a quote needs to record
            // which wallet paid it and the transaction id.
            // ---------------------------------------------------------------
            [
                'code'        => 'payment_method',
                'name'        => 'Payment Method',
                'type'        => 'select',
                'entity_type' => 'quotes',
                'sort_order'  => 14,
                'options'     => [
                    'M-Pesa',
                    'Tigo Pesa',
                    'Airtel Money',
                    'Halopesa',
                    'T-Pesa',
                    'Bank Transfer',
                    'Cash',
                ],
            ],
            [
                'code'        => 'payment_reference',
                'name'        => 'Payment Reference (TxID)',
                'type'        => 'text',
                'entity_type' => 'quotes',
                'sort_order'  => 15,
            ],
        ];
    }

    public function run(): void
    {
        $now = Carbon::now();

        foreach ($this->definitions() as $definition) {
            $options = $definition['options'] ?? [];

            unset($definition['options']);

            // updateOrInsert on (code, entity_type) is what makes a re-run
            // harmless. created_at is deliberately NOT in the update payload:
            // repairing an existing row should not rewrite when it was made.
            DB::table('attributes')->updateOrInsert(
                [
                    'code'        => $definition['code'],
                    'entity_type' => $definition['entity_type'],
                ],
                array_merge($definition, [
                    'lookup_type'     => null,
                    'validation'      => null,
                    'is_required'     => 0,
                    'is_unique'       => 0,
                    'quick_add'       => 0,
                    // Not user-defined: these ship with the product, and
                    // marking them user-defined would let the attribute UI
                    // offer to delete them.
                    'is_user_defined' => 0,
                    'updated_at'      => $now,
                ])
            );

            if ($options === []) {
                continue;
            }

            $this->syncOptions($definition['code'], $definition['entity_type'], $options);
        }
    }

    /**
     * Options are matched on (attribute_id, name) so re-running neither
     * duplicates them nor disturbs any a tenant added themselves.
     */
    private function syncOptions(string $code, string $entityType, array $options): void
    {
        $attributeId = DB::table('attributes')
            ->where('code', $code)
            ->where('entity_type', $entityType)
            ->value('id');

        if (! $attributeId) {
            return;
        }

        foreach ($options as $sort => $name) {
            DB::table('attribute_options')->updateOrInsert(
                [
                    'attribute_id' => $attributeId,
                    'name'         => $name,
                ],
                ['sort_order' => $sort + 1]
            );
        }
    }

    /**
     * How many of this seeder's attributes are actually present.
     *
     * Used by the test that stops 9.1 regressing, and worth having for a
     * health check: the failure mode here was silence, so it needs something
     * that can be asked out loud.
     */
    public function presentCount(): int
    {
        $count = 0;

        foreach ($this->definitions() as $definition) {
            $count += DB::table('attributes')
                ->where('code', $definition['code'])
                ->where('entity_type', $definition['entity_type'])
                ->exists() ? 1 : 0;
        }

        return $count;
    }
}
