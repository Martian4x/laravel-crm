<?php

use Azimio\Tenancy\Database\Seeders\AzimioAttributeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 9.1.4 — the attributes we add must actually be present.
 *
 * This exists because they were not, for months, on every installed system.
 * `krayin-crm:install` runs `migrate:fresh` and then seeders whose first act
 * is `DB::table('attributes')->delete()`, so attributes inserted by a
 * migration were wiped while the migration stayed recorded as run. 5.4 and
 * 5.5 were both ticked off and neither had ever worked.
 *
 * The failure mode was silence. These tests are the noise.
 */
it('has every Azimio attribute present in the database', function () {
    $seeder = new AzimioAttributeSeeder;

    $missing = collect($seeder->definitions())
        ->reject(fn (array $d) => DB::table('attributes')
            ->where('code', $d['code'])
            ->where('entity_type', $d['entity_type'])
            ->exists())
        ->map(fn (array $d) => $d['entity_type'].'.'.$d['code'])
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('gives organizations the company-profile fields they had none of', function () {
    // Before 9.2 an organization was name + address + sales owner. No phone
    // number, no email address, no taxpayer identification.
    $codes = DB::table('attributes')
        ->where('entity_type', 'organizations')
        ->pluck('code')
        ->all();

    expect($codes)->toContain('phone', 'email', 'tin', 'vrn', 'business_type', 'location');
});

it('keeps the mobile-money fields on quotes', function () {
    $codes = DB::table('attributes')
        ->where('entity_type', 'quotes')
        ->pluck('code')
        ->all();

    expect($codes)->toContain('payment_method', 'payment_reference');
});

it('seeds the select options exactly once however often it runs', function () {
    $seeder = new AzimioAttributeSeeder;

    $count = fn (string $code) => DB::table('attribute_options')
        ->where('attribute_id', DB::table('attributes')->where('code', $code)->value('id'))
        ->count();

    expect($count('business_type'))->toBe(8)
        ->and($count('payment_method'))->toBe(7);

    // The seeder runs from two places -- a migration and the
    // `krayin.installed` listener -- and both can fire on the same database.
    // Re-running must be a no-op, not a way to collect duplicate options.
    $seeder->run();
    $seeder->run();

    expect($count('business_type'))->toBe(8)
        ->and($count('payment_method'))->toBe(7);
});

it('does not duplicate attributes when the seeder runs again', function () {
    $seeder = new AzimioAttributeSeeder;

    $before = DB::table('attributes')->count();

    $seeder->run();

    expect(DB::table('attributes')->count())->toBe($before);
});

it('registers them as shared, so every tenant sees them', function () {
    // `attributes` is TenantTables::SHARED: a NULL tenant_id row is a
    // platform default visible to everyone. A tenant_id here would hide the
    // field from every tenant but one.
    $scoped = DB::table('attributes')
        ->whereIn('code', ['tin', 'vrn', 'business_type', 'location', 'payment_method'])
        ->whereNotNull('tenant_id')
        ->count();

    expect($scoped)->toBe(0);
});

it('gives users somewhere to put a phone number', function () {
    // 9.3 -- also what 8.20's per-user staff alert SMS was blocked on.
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'phone2'))->toBeTrue();
});
