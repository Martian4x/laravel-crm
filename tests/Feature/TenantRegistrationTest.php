<?php

use Azimio\Tenancy\Mail\VerifyTenantEmail;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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

/**
 * CHANGED BY 7.2: registration no longer signs the admin in. The company is
 * created pending and stays unusable until the address is confirmed —
 * signing them in regardless would make the check decorative.
 */
it('registers a company as pending and does not sign the admin in', function () {
    Mail::fake();

    registerRegTestCo();

    $tenant = Tenant::where('slug', 'reg-test-co')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->locale)->toBe('sw')
        // Not suspended — simply not finished signing up.
        ->and($tenant->isActive())->toBeTrue()
        ->and($tenant->isPending())->toBeTrue()
        ->and($tenant->isUsable())->toBeFalse()
        // 7.5: a self-registered company starts on a trial.
        ->and($tenant->trial_ends_at)->not->toBeNull();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();

    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->is_super_admin)->toBe(0)
        ->and($admin->locale)->toBe('sw')
        ->and($admin->email_verified_at)->toBeNull()
        ->and(auth()->guard('user')->check())->toBeFalse();

    Mail::assertQueued(VerifyTenantEmail::class);
});

function registerRegTestCo()
{
    return test()->post(route('azimio.register.store'), [
        'company_name'          => 'Reg Test Co',
        'name'                  => 'Reg Admin',
        'email'                 => 'admin@reg-test.co.tz',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'locale'                => 'sw',
        'terms'                 => '1',
    ]);
}

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

it('activates the company when the signed link is opened', function () {
    Mail::fake();

    registerRegTestCo();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();

    test()->get(URL::temporarySignedRoute('azimio.verify.verify', now()->addDay(), ['id' => $admin->id]))
        ->assertRedirect(route('admin.dashboard.index'));

    $tenant = Tenant::where('slug', 'reg-test-co')->first();

    expect($tenant->isPending())->toBeFalse()
        ->and($tenant->isUsable())->toBeTrue()
        ->and($admin->fresh()->email_verified_at)->not->toBeNull()
        ->and(auth()->guard('user')->user()->email)->toBe('admin@reg-test.co.tz');
});

it('refuses an unsigned or tampered verification link', function () {
    // The signature is what stops someone verifying an account that is not
    // theirs by editing the id in the URL.
    Mail::fake();

    registerRegTestCo();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();

    test()->get(route('azimio.verify.verify', ['id' => $admin->id]))
        ->assertOK()
        ->assertSee(trans('azimio::app.verify.expired-title'));

    expect(Tenant::where('slug', 'reg-test-co')->first()->isPending())->toBeTrue()
        ->and($admin->fresh()->email_verified_at)->toBeNull();
});

it('tolerates the same link being opened twice', function () {
    // People forward these mails to themselves.
    Mail::fake();

    registerRegTestCo();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();
    $url = URL::temporarySignedRoute('azimio.verify.verify', now()->addDay(), ['id' => $admin->id]);

    test()->get($url)->assertRedirect(route('admin.dashboard.index'));

    $firstVerifiedAt = $admin->fresh()->email_verified_at;

    test()->get($url)->assertRedirect(route('admin.dashboard.index'));

    // Compared as strings: Krayin's User model has no cast for this
    // column, and this module's own code only checks it for truthiness,
    // so adding one upstream purely for a test would be the wrong trade.
    expect((string) $admin->fresh()->email_verified_at)->toBe((string) $firstVerifiedAt);
});

it('sends a super-admin-created tenant straight through, already verified', function () {
    // A tenant the platform created is vouched for; making a super admin
    // click a link to activate their own work would be theatre.
    $tenant = app(Azimio\Tenancy\TenantOnboarder::class)->onboard(
        ['name' => 'Reg Test Co', 'locale' => 'en'],
        ['name' => 'Reg Admin', 'email' => 'admin@reg-test.co.tz', 'password' => 'secret123'],
    );

    expect($tenant->isPending())->toBeFalse()
        ->and($tenant->isUsable())->toBeTrue()
        ->and(User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->value('email_verified_at'))
        ->not->toBeNull();
});

it('does not reveal whether an address exists when resending', function () {
    // Otherwise this endpoint enumerates registered email addresses.
    Mail::fake();

    test()->post(route('azimio.verify.resend'), ['email' => 'nobody@nowhere.test'])
        ->assertRedirect(route('azimio.verify.notice'))
        ->assertSessionHas('success', trans('azimio::app.verify.resent'));

    Mail::assertNotQueued(VerifyTenantEmail::class);
});

it('sends a pending tenant to the verification notice, not a suspension error', function () {
    // A pending company has not been refused, so it must not get the
    // "suspended" message, which they could do nothing about.
    Mail::fake();

    registerRegTestCo();

    $admin = User::withoutTenancy()->where('email', 'admin@reg-test.co.tz')->first();

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertRedirect(route('azimio.verify.notice'));
});
