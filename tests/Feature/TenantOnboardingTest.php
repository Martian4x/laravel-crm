<?php

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\OnboardingChecklist;
use Azimio\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Models\CoreConfig;
use Webkul\User\Models\User;

/**
 * Phase 7.6 — the first-login checklist.
 *
 * Steps are DERIVED from real data rather than ticked off by a flag, so the
 * card cannot claim something is done when it is not, and un-ticks itself
 * if whatever satisfied a step is later removed. A stored "completed" flag
 * would drift the first time someone deleted their logo.
 */
function onbAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function onbTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

function onbConfig(string $code, string $value): void
{
    $config = new CoreConfig;

    $config->forceFill([
        'code'      => $code,
        'value'     => $value,
        'tenant_id' => onbTenant()->id,
    ])->save();
}

afterEach(function () {
    CoreConfig::withoutTenancy()
        ->where('tenant_id', onbTenant()->id)
        ->whereIn('code', [
            'azimio.onboarding.dismissed',
            'admin_logo.logo_image',
            'general.company_information.tin',
        ])
        ->forceDelete();

    DB::table('users')->where('tenant_id', onbTenant()->id)
        ->where('email', 'like', '%@onb-test.local')->delete();

    app(TenantManager::class)->forget();
});

it('derives each step from real data', function () {
    app(TenantManager::class)->set(onbTenant());

    $steps = collect(app(OnboardingChecklist::class)->steps(onbTenant()))->keyBy('key');

    expect($steps->keys()->all())->toBe(['logo', 'company-info', 'team', 'first-lead'])
        ->and($steps['logo']['done'])->toBeFalse()
        ->and($steps['company-info']['done'])->toBeFalse();
});

it('ticks the team step only once a second user exists', function () {
    app(TenantManager::class)->set(onbTenant());

    $checklist = app(OnboardingChecklist::class);

    $before = collect($checklist->steps(onbTenant()))->keyBy('key')['team']['done'];

    DB::table('users')->insert([
        'name'       => 'ONB Colleague', 'email' => 'colleague@onb-test.local',
        'password'   => bcrypt('secret123'), 'tenant_id' => onbTenant()->id,
        'role_id'    => DB::table('roles')->whereNull('tenant_id')->value('id'),
        'status'     => 1, 'is_super_admin' => 0, 'view_permission' => 'global',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $after = collect($checklist->steps(onbTenant()))->keyBy('key')['team']['done'];

    // The seeded tenant already has its own admin, so the step turns on
    // only when somebody else is added.
    expect($before)->toBeFalse()->and($after)->toBeTrue();
});

it('counts progress and reports completeness', function () {
    app(TenantManager::class)->set(onbTenant());

    $checklist = app(OnboardingChecklist::class);

    expect($checklist->completedCount(onbTenant()))->toBeLessThan(4)
        ->and($checklist->isComplete(onbTenant()))->toBeFalse()
        ->and($checklist->shouldShow(onbTenant()))->toBeTrue();
});

it('hides the card once dismissed', function () {
    app(TenantManager::class)->set(onbTenant());

    $checklist = app(OnboardingChecklist::class);

    expect($checklist->shouldShow(onbTenant()))->toBeTrue();

    $checklist->dismiss();

    // Someone who does not want it should not have to finish it to be rid
    // of it.
    expect($checklist->isDismissed())->toBeTrue()
        ->and($checklist->shouldShow(onbTenant()))->toBeFalse();
});

it('shows the card on the dashboard through the render hook', function () {
    test()->actingAs(onbAdmin())
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertSee(trans('azimio::app.onboarding.step-first-lead'));
});

it('dismisses from the dashboard and stops showing it', function () {
    test()->actingAs(onbAdmin())
        ->post(route('azimio.onboarding.dismiss'))
        ->assertRedirect();

    test()->actingAs(onbAdmin())
        ->get(route('admin.dashboard.index'))
        ->assertOK()
        ->assertDontSee(trans('azimio::app.onboarding.step-first-lead'));
});

it('keeps one company dismissal out of another', function () {
    // core_config is tenant-scoped (3.8), so dismissing must not hide the
    // card from everybody.
    app(TenantManager::class)->set(onbTenant());
    app(OnboardingChecklist::class)->dismiss();

    $other = Tenant::withoutGlobalScopes()->where('slug', 'kilimanjaro-suppliers')->firstOrFail();

    app(TenantManager::class)->set($other);

    expect(app(OnboardingChecklist::class)->isDismissed())->toBeFalse();
});
