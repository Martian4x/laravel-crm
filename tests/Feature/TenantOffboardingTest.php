<?php

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantEraser;
use Azimio\Tenancy\TenantExporter;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

/**
 * Phase 7.12 / 7.13 — tenant data export and guarded deletion.
 *
 * Deletion used to be immediate, so one mis-click destroyed a customer's
 * data with no way back. These tests pin the three things now standing in
 * the way: the tenant must be suspended, the grace period must have run,
 * and the name must be typed.
 */
function offSuper(): User
{
    return User::withoutTenancy()->where('is_super_admin', 1)->firstOrFail();
}

function offTenant(): Tenant
{
    $tenant = Tenant::withoutGlobalScopes()->firstOrCreate(
        ['slug' => 'offboard-test'],
        ['name' => 'Offboard Test Ltd']
    );

    $tenant->forceFill([
        'activated_at'      => now(),
        'status'            => Tenant::STATUS_ACTIVE,
        'suspended_at'      => null,
        'suspension_reason' => null,
    ])->save();

    return $tenant->fresh();
}

function offSuspend(Tenant $tenant, int $daysAgo): void
{
    $tenant->forceFill([
        'status'       => Tenant::STATUS_SUSPENDED,
        'suspended_at' => now()->subDays($daysAgo),
    ])->save();
}

function offPerson(Tenant $tenant, string $name): void
{
    DB::table('persons')->insert([
        'tenant_id'  => $tenant->id,
        'name'       => $name,
        'emails'     => json_encode([['value' => 'p@offboard.test', 'label' => 'work']]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function offZipNames(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);

    $names = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    $zip->close();

    return $names;
}

function offZipFile(string $path, string $name): string|false
{
    $zip = new ZipArchive;
    $zip->open($path);
    $content = $zip->getFromName($name);
    $zip->close();

    return $content;
}

afterEach(function () {
    if ($tenant = Tenant::withoutGlobalScopes()->where('slug', 'offboard-test')->first()) {
        Person::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();
        User::withoutTenancy()->where('tenant_id', $tenant->id)->forceDelete();

        $tenant->delete();
    }

    foreach (glob(storage_path('app/tenant-export-*.zip')) as $leftover) {
        @unlink($leftover);
    }
});

it('exports a tenant as a zip of CSVs', function () {
    $tenant = offTenant();

    offPerson($tenant, 'Offboard Person');

    $path = app(TenantExporter::class)->export($tenant);

    expect(file_exists($path))->toBeTrue()
        ->and(offZipNames($path))->toContain('README.txt', 'persons.csv');

    $csv = offZipFile($path, 'persons.csv');

    expect($csv)->toContain('Offboard Person')
        // JSON columns are kept as JSON rather than invented into columns.
        ->and($csv)->toContain('p@offboard.test');

    @unlink($path);
});

it('never exports passwords or credentials', function () {
    $tenant = offTenant();

    DB::table('users')->insert([
        'name'     => 'Offboard Admin', 'email' => 'admin@offboard.test',
        'password' => bcrypt('secret-password-value'), 'tenant_id' => $tenant->id,
        // users.role_id carries a foreign key, so a real role is required.
        'role_id'    => DB::table('roles')->whereNull('tenant_id')->value('id'),
        'status'     => 1, 'is_super_admin' => 0, 'view_permission' => 'global',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $path = app(TenantExporter::class)->export($tenant);

    $csv = offZipFile($path, 'users.csv');

    expect($csv)->toContain('admin@offboard.test')
        // The header must not even offer the column.
        ->and($csv)->not->toContain('password');

    @unlink($path);
});

it('omits tables with no data rather than shipping empty files', function () {
    $tenant = offTenant();

    $path = app(TenantExporter::class)->export($tenant);

    expect(offZipFile($path, 'quotes.csv'))->toBeFalse()
        ->and(offZipFile($path, 'README.txt'))->not->toBeFalse();

    @unlink($path);
});

it('lets a super admin download the export', function () {
    $tenant = offTenant();

    test()->actingAs(offSuper())
        ->get(route('super.tenants.export', $tenant->id))
        ->assertOK()
        ->assertHeader('content-type', 'application/zip');
});

it('refuses to delete a tenant that is still active', function () {
    // Deleting an account somebody is using is almost always a mistake.
    $tenant = offTenant();

    expect(app(TenantEraser::class)->canDelete($tenant))->toBeFalse();

    test()->actingAs(offSuper())
        ->delete(route('super.tenants.destroy', $tenant->id), ['confirm_name' => $tenant->name])
        ->assertRedirect(route('super.tenants.edit', $tenant->id))
        ->assertSessionHas('error');

    expect(Tenant::withoutGlobalScopes()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('refuses to delete while the grace period is still running', function () {
    $tenant = offTenant();
    offSuspend($tenant, 1);

    $eraser = app(TenantEraser::class);

    expect($eraser->canDelete($tenant->fresh()))->toBeFalse()
        ->and($eraser->blockedReason($tenant->fresh()))->toContain('grace period');

    test()->actingAs(offSuper())
        ->delete(route('super.tenants.destroy', $tenant->id), ['confirm_name' => $tenant->name])
        ->assertSessionHas('error');

    expect(Tenant::withoutGlobalScopes()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('refuses to delete when the typed name does not match', function () {
    // A yes/no dialog is too easy to click through for something final.
    $tenant = offTenant();
    offSuspend($tenant, 30);

    test()->actingAs(offSuper())
        ->delete(route('super.tenants.destroy', $tenant->id), ['confirm_name' => 'Wrong Name'])
        ->assertSessionHas('error', trans('azimio::app.tenants.delete-name-mismatch'));

    expect(Tenant::withoutGlobalScopes()->whereKey($tenant->id)->exists())->toBeTrue();
});

it('deletes once suspended, past grace, and name confirmed', function () {
    $tenant = offTenant();

    offPerson($tenant, 'Offboard Person');
    offSuspend($tenant, 30);

    test()->actingAs(offSuper())
        ->delete(route('super.tenants.destroy', $tenant->id), ['confirm_name' => $tenant->name])
        ->assertRedirect(route('super.tenants.index'))
        ->assertSessionHas('success');

    expect(Tenant::withoutGlobalScopes()->whereKey($tenant->id)->exists())->toBeFalse()
        // FK cascades take the child rows with it.
        ->and(DB::table('persons')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('counts what it removed before removing it', function () {
    // Afterwards there is nothing to count, and "we deleted something" is
    // not an audit record.
    $tenant = offTenant();

    offPerson($tenant, 'P1');
    offPerson($tenant, 'P2');
    offSuspend($tenant, 30);

    $removed = app(TenantEraser::class)->erase($tenant->fresh());

    expect($removed)->toHaveKey('persons')
        ->and($removed['persons'])->toBe(2);
});

it('reports days remaining so the UI can say which rule is in the way', function () {
    $tenant = offTenant();

    $eraser = app(TenantEraser::class);

    // Active: the full grace period still lies ahead.
    expect($eraser->daysRemaining($tenant))->toBe($eraser->graceDays());

    offSuspend($tenant, 5);

    expect($eraser->daysRemaining($tenant->fresh()))->toBe($eraser->graceDays() - 5);

    offSuspend($tenant, 99);

    expect($eraser->daysRemaining($tenant->fresh()))->toBe(0);
});

it('shows the offboarding panel on the edit page', function () {
    $tenant = offTenant();

    test()->actingAs(offSuper())
        ->get(route('super.tenants.edit', $tenant->id))
        ->assertOK()
        ->assertSee(trans('azimio::app.tenants.offboarding'))
        ->assertSee(trans('azimio::app.tenants.export-data'))
        // Active, so the delete form must not be offered at all.
        ->assertSee(trans('azimio::app.tenants.delete-unavailable'));
});
