<?php

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

afterEach(function () {
    if ($tenant = Tenant::where('slug', 'reg-test-co')->first()) {
        DB::table('users')->where('tenant_id', $tenant->id)->delete();

        $tenant->delete();
    }
});

it('shows the public registration page with terms links', function () {
    test()->get(route('azimio.register.create'))
        ->assertOK()
        ->assertSee(route('azimio.terms'))
        ->assertSee(route('azimio.privacy'));
});

it('shows the terms and privacy pages', function () {
    test()->get(route('azimio.terms'))->assertOK();

    test()->get(route('azimio.privacy'))->assertOK();
});

it('registers a company, logs the admin in, and scopes them', function () {
    test()->post(route('azimio.register.store'), [
        'company_name'          => 'Reg Test Co',
        'name'                  => 'Reg Admin',
        'email'                 => 'admin@reg-test.co.tz',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'locale'                => 'sw',
        'terms'                 => '1',
    ])->assertRedirect(route('admin.dashboard.index'));

    $tenant = Tenant::where('slug', 'reg-test-co')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->locale)->toBe('sw')
        ->and($tenant->isActive())->toBeTrue();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();

    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->is_super_admin)->toBe(0)
        ->and($admin->locale)->toBe('sw')
        ->and(auth()->guard('user')->user()->email)->toBe('admin@reg-test.co.tz');
});

it('rejects registration without accepted terms', function () {
    test()->post(route('azimio.register.store'), [
        'company_name'          => 'Reg Test Co',
        'name'                  => 'Reg Admin',
        'email'                 => 'admin@reg-test.co.tz',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'locale'                => 'sw',
    ])->assertSessionHasErrors('terms');

    expect(Tenant::where('slug', 'reg-test-co')->exists())->toBeFalse();
});

it('silently drops bot submissions that fill the honeypot', function () {
    test()->post(route('azimio.register.store'), [
        'company_name'          => 'Reg Test Co',
        'name'                  => 'Reg Admin',
        'email'                 => 'admin@reg-test.co.tz',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'locale'                => 'sw',
        'terms'                 => '1',
        'website'               => 'http://spam.example',
    ])->assertRedirect(route('admin.session.create'));

    expect(Tenant::where('slug', 'reg-test-co')->exists())->toBeFalse();
});

it('rejects duplicate emails across tenants', function () {
    test()->post(route('azimio.register.store'), [
        'company_name'          => 'Reg Test Co',
        'name'                  => 'Reg Admin',
        'email'                 => 'admin@twiga-traders.test',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'locale'                => 'en',
        'terms'                 => '1',
    ])->assertSessionHasErrors('email');

    expect(Tenant::where('slug', 'reg-test-co')->exists())->toBeFalse();
});
