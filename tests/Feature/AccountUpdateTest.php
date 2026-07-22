<?php

use Illuminate\Support\Facades\Hash;
use Webkul\User\Models\User;

afterEach(function () {
    // Restore the dev tenant admin's name/password if a test changed them.
    User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->update([
        'name'     => 'Twiga Traders Ltd Admin',
        'password' => bcrypt('password'),
    ]);
});

it('updates account details without requiring the password', function () {
    $user = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($user)
        ->put(route('admin.user.account.update'), [
            'name'  => 'Renamed Admin',
            'email' => $user->email,
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Renamed Admin');
});

it('shows the dedicated change-password page', function () {
    $user = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($user)
        ->get(route('admin.user.account.password.edit'))
        ->assertOK();
});

it('changes the password on the password page', function () {
    $user = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($user)
        ->put(route('admin.user.account.password.update'), [
            'current_password'      => 'password',
            'password'              => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])
        ->assertRedirect(route('admin.user.account.password.edit'));

    expect(Hash::check('newsecret1', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $user = User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->first();

    test()->actingAs($user)
        ->put(route('admin.user.account.password.update'), [
            'current_password'      => 'wrongpass',
            'password'              => 'newsecret1',
            'password_confirmation' => 'newsecret1',
        ])
        ->assertRedirect();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
