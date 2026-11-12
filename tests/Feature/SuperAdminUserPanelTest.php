<?php

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * Clean up any users these tests create, no matter how they exit.
 */
afterEach(function () {
    User::withoutTenancy()->where('email', 'like', '%@super-panel-test.local')->forceDelete();
});

function platformRoleId(): int
{
    return Role::withoutTenancy()->whereNull('tenant_id')->orderBy('id')->value('id');
}

function twiga(): Tenant
{
    return Tenant::where('slug', 'twiga-traders')->first();
}

/**
 * tenant_id is not mass-assignable on User, so build the fixture via forceFill.
 */
function makeTenantUser(string $email, int $tenantId): User
{
    $user = new User;

    $user->forceFill([
        'name'      => 'Fixture User',
        'email'     => $email,
        'password'  => Hash::make('secret123'),
        'tenant_id' => $tenantId,
        'role_id'   => platformRoleId(),
        'status'    => 1,
    ])->save();

    return $user;
}

it('lets the super admin view the users panel across tenants', function () {
    test()->actingAs(getDefaultAdmin())
        ->get(route('super.users.index'))
        ->assertOK()
        ->assertSee('admin@twiga-traders.test');
});

it('hides the users panel from tenant users', function () {
    $tenantAdmin = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($tenantAdmin)
        ->get(route('super.users.index'))
        ->assertNotFound();
});

it('lets the super admin create a user under a tenant', function () {
    test()->actingAs(getDefaultAdmin())
        ->post(route('super.users.store'), [
            'name'      => 'Created User',
            'email'     => 'created@super-panel-test.local',
            'password'  => 'secret123',
            'tenant_id' => twiga()->id,
            'role_id'   => platformRoleId(),
            'locale'    => 'sw',
            'status'    => '1',
        ])
        ->assertRedirect(route('super.users.index'));

    $user = User::withoutTenancy()->where('email', 'created@super-panel-test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe(twiga()->id)
        ->and($user->locale)->toBe('sw')
        ->and((int) $user->status)->toBe(1);
});

it('lets the super admin move a user to another tenant and change info', function () {
    $user = makeTenantUser('movable@super-panel-test.local', twiga()->id);

    $other = Tenant::where('slug', 'kilimanjaro-suppliers')->first();

    test()->actingAs(getDefaultAdmin())
        ->put(route('super.users.update', $user->id), [
            'name'      => 'Renamed User',
            'email'     => 'movable@super-panel-test.local',
            'tenant_id' => $other->id,
            'role_id'   => platformRoleId(),
            'status'    => '1',
        ])
        ->assertRedirect(route('super.users.index'));

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Renamed User')
        ->and($fresh->tenant_id)->toBe($other->id);
});

it('lets the super admin reset a user password', function () {
    $user = makeTenantUser('pwd@super-panel-test.local', twiga()->id);

    test()->actingAs(getDefaultAdmin())
        ->put(route('super.users.password', $user->id), [
            'password'              => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])
        ->assertRedirect(route('super.users.edit', $user->id));

    expect(Hash::check('newsecret1', $user->fresh()->password))->toBeTrue();
});

it('lets the super admin delete a user', function () {
    $user = makeTenantUser('deletable@super-panel-test.local', twiga()->id);

    test()->actingAs(getDefaultAdmin())
        ->delete(route('super.users.destroy', $user->id))
        ->assertRedirect(route('super.users.index'));

    expect(User::withoutTenancy()->find($user->id))->toBeNull();
});

it('prevents the super admin from deleting their own account', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->delete(route('super.users.destroy', $admin->id))
        ->assertRedirect(route('super.users.index'));

    expect(User::withoutTenancy()->find($admin->id))->not->toBeNull();
});

it('rejects assigning a role that belongs to a different tenant', function () {
    // A role owned by Kilimanjaro must not be assignable to a Twiga user.
    $other = Tenant::where('slug', 'kilimanjaro-suppliers')->first();

    $foreignRole = new Role;

    $foreignRole->forceFill([
        'name'            => 'Foreign Role SPT',
        'permission_type' => 'all',
        'tenant_id'       => $other->id,
    ])->save();

    test()->actingAs(getDefaultAdmin())
        ->post(route('super.users.store'), [
            'name'      => 'Bad Role User',
            'email'     => 'badrole@super-panel-test.local',
            'password'  => 'secret123',
            'tenant_id' => twiga()->id,
            'role_id'   => $foreignRole->id,
            'status'    => '1',
        ])
        ->assertStatus(422);

    expect(User::withoutTenancy()->where('email', 'badrole@super-panel-test.local')->exists())->toBeFalse();

    $foreignRole->forceDelete();
});

/**
 * Regression: 'locale' is a nullable field, so Laravel omits it from the
 * validated array entirely when the request does not send it. Reading it with
 * ?: then raised "Undefined array key" and returned a 500. Both the create and
 * the update path are covered here, since only update had a test that happened
 * to omit the field.
 */
it('creates a user when no locale is submitted', function () {
    test()->actingAs(getDefaultAdmin())
        ->post(route('super.users.store'), [
            'name'      => 'No Locale User',
            'email'     => 'nolocale@super-panel-test.local',
            'password'  => 'secret123',
            'tenant_id' => twiga()->id,
            'role_id'   => platformRoleId(),
            'status'    => '1',
        ])
        ->assertRedirect(route('super.users.index'));

    $user = User::withoutTenancy()->where('email', 'nolocale@super-panel-test.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale)->toBeNull();
});

it('updates a user when no locale is submitted', function () {
    $user = makeTenantUser('nolocale-update@super-panel-test.local', twiga()->id);

    test()->actingAs(getDefaultAdmin())
        ->put(route('super.users.update', $user->id), [
            'name'      => 'Still Here',
            'email'     => 'nolocale-update@super-panel-test.local',
            'tenant_id' => twiga()->id,
            'role_id'   => platformRoleId(),
            'status'    => '1',
        ])
        ->assertRedirect(route('super.users.index'));

    expect($user->fresh()->name)->toBe('Still Here')
        ->and($user->fresh()->locale)->toBeNull();
});

it('clears the locale back to null when an empty value is submitted', function () {
    $user = makeTenantUser('emptylocale@super-panel-test.local', twiga()->id);

    $user->forceFill(['locale' => 'sw'])->save();

    test()->actingAs(getDefaultAdmin())
        ->put(route('super.users.update', $user->id), [
            'name'      => 'Fixture User',
            'email'     => 'emptylocale@super-panel-test.local',
            'tenant_id' => twiga()->id,
            'role_id'   => platformRoleId(),
            'locale'    => '',
            'status'    => '1',
        ])
        ->assertRedirect(route('super.users.index'));

    expect($user->fresh()->locale)->toBeNull();
});
