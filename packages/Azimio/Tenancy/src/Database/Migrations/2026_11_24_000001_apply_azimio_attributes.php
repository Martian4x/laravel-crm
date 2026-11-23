<?php

use Azimio\Tenancy\Database\Seeders\AzimioAttributeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair databases that already exist (9.1.3).
     *
     * On a FRESH install this migration is pointless and harmless: it runs
     * during `migrate:fresh`, and AttributeSeeder deletes everything in
     * `attributes` moments later. The `krayin.installed` listener is what
     * covers that case.
     *
     * On an EXISTING database — production az1, every developer machine —
     * no installer is going to run again, so this is the only thing that
     * puts the missing attributes back.
     *
     * Both paths call the same idempotent seeder, so running one, the other,
     * or both in either order gives the same result.
     *
     * @return void
     */
    public function up()
    {
        (new AzimioAttributeSeeder)->run();
    }

    /**
     * Reverse the migrations.
     *
     * Deletes the attribute definitions and their options, and ANY VALUES
     * TENANTS HAVE ENTERED against them — a TIN typed into a company record
     * lives in attribute_values and is orphaned otherwise.
     *
     * That makes this genuinely destructive, which is the honest shape of
     * rolling back a field: the field's data goes with it.
     *
     * @return void
     */
    public function down()
    {
        $seeder = new AzimioAttributeSeeder;

        foreach ($seeder->definitions() as $definition) {
            $id = DB::table('attributes')
                ->where('code', $definition['code'])
                ->where('entity_type', $definition['entity_type'])
                ->value('id');

            if (! $id) {
                continue;
            }

            DB::table('attribute_options')->where('attribute_id', $id)->delete();
            DB::table('attribute_values')->where('attribute_id', $id)->delete();
            DB::table('attributes')->where('id', $id)->delete();
        }
    }
};
