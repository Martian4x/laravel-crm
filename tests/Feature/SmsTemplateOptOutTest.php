<?php

use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Models\Template;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Webkul\User\Models\User;

/**
 * Phase 8.9 / 8.11 — templates and opt-out management.
 *
 * The behaviours worth pinning: a tenant cannot edit a shared platform
 * template, and every number entering the opt-out list is normalised, since
 * a suppression stored in a different format would not match a send and the
 * contact would be messaged anyway.
 */
function tplAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function tplTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

afterEach(function () {
    Template::withoutTenancy()->where('name', 'like', 'TPL Test%')->forceDelete();
    OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->forceDelete();
});

function platformTemplate(): Template
{
    return Template::withoutTenancy()->firstOrCreate(
        ['tenant_id' => null, 'name' => 'TPL Test Platform'],
        ['body' => 'Karibu {{person.first_name}}', 'category' => 'transactional'],
    );
}

it('renders the template list with the per-send cost', function () {
    platformTemplate();

    test()->actingAs(tplAdmin())
        ->get(route('admin.sms.templates.index'))
        ->assertOK()
        ->assertSee('TPL Test Platform')
        ->assertSee(trans('messaging::app.templates.platform'));
});

it('creates a tenant template', function () {
    test()->actingAs(tplAdmin())
        ->post(route('admin.sms.templates.store'), [
            'name'     => 'TPL Test Mine',
            'body'     => 'Habari {{person.first_name}}',
            'category' => 'marketing',
        ])
        ->assertRedirect(route('admin.sms.templates.index'))
        ->assertSessionHas('success');

    $template = Template::withoutTenancy()->where('name', 'TPL Test Mine')->first();

    expect($template)->not->toBeNull()
        ->and($template->tenant_id)->toBe(tplTenant()->id)
        ->and($template->category->value)->toBe('marketing');
});

it('shows a platform template to a tenant but refuses an edit', function () {
    $platform = platformTemplate();

    // Visible...
    test()->actingAs(tplAdmin())
        ->get(route('admin.sms.templates.index'))
        ->assertOK()
        ->assertSee('TPL Test Platform');

    // ...but not editable, and said so rather than silently failing.
    test()->actingAs(tplAdmin())
        ->get(route('admin.sms.templates.edit', $platform->id))
        ->assertRedirect(route('admin.sms.templates.index'))
        ->assertSessionHas('error', trans('messaging::app.templates.platform-read-only'));

    test()->actingAs(tplAdmin())
        ->put(route('admin.sms.templates.update', $platform->id), [
            'name' => 'Hijacked', 'body' => 'Changed', 'category' => 'transactional',
        ])
        ->assertSessionHas('error');

    expect($platform->fresh()->name)->toBe('TPL Test Platform');
});

it('refuses to delete a platform template', function () {
    $platform = platformTemplate();

    test()->actingAs(tplAdmin())
        ->delete(route('admin.sms.templates.destroy', $platform->id))
        ->assertSessionHas('error');

    expect(Template::withoutTenancy()->whereKey($platform->id)->exists())->toBeTrue();
});

it('counts template segments from the same counter the pipeline bills with', function () {
    test()->actingAs(tplAdmin())
        ->postJson(route('admin.sms.templates.preview'), ['body' => str_repeat('a', 161)])
        ->assertOK()
        ->assertJson(['length' => 161, 'segments' => 2, 'encoding' => 'gsm7']);
});

it('renders the opt-out page', function () {
    test()->actingAs(tplAdmin())
        ->get(route('admin.sms.opt-outs.index'))
        ->assertOK()
        ->assertSee(trans('messaging::app.opt-outs.help'));
});

it('normalises a number added by hand', function () {
    // Stored as typed, a suppression would not match a send addressed to
    // the E.164 form, and the contact would be messaged anyway.
    test()->actingAs(tplAdmin())
        ->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0712 345 678'])
        ->assertRedirect(route('admin.sms.opt-outs.index'))
        ->assertSessionHas('success');

    expect(OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->value('dest_addr'))
        ->toBe('+255712345678');
});

it('rejects a number it cannot read, with the reason', function () {
    test()->actingAs(tplAdmin())
        ->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0222110000'])
        ->assertSessionHas('error', trans('messaging::app.sms.phone.not-mobile'));

    expect(OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->count())->toBe(0);
});

it('does not duplicate a number already on the list', function () {
    test()->actingAs(tplAdmin())->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0712345678']);

    // Same number, different spelling: the unique index must not throw.
    test()->actingAs(tplAdmin())
        ->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '+255 712 345 678'])
        ->assertSessionHas('success');

    expect(OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->count())->toBe(1);
});

it('imports a list and reports what happened to each line', function () {
    test()->actingAs(tplAdmin())->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0712345678']);

    test()->actingAs(tplAdmin())
        ->post(route('admin.sms.opt-outs.import'), [
            'numbers' => "0713345678\n0714345678\n0712345678\n0222110000\nrubbish",
        ])
        ->assertRedirect(route('admin.sms.opt-outs.index'))
        ->assertSessionHas('success')
        // The unreadable lines come back so the user can fix their list.
        ->assertSessionHas('invalid_numbers');

    expect(OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->count())->toBe(3);
});

it('removes a suppression so a customer can be messaged again', function () {
    test()->actingAs(tplAdmin())->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0712345678']);

    $optOut = OptOut::withoutTenancy()->where('tenant_id', tplTenant()->id)->first();

    test()->actingAs(tplAdmin())
        ->delete(route('admin.sms.opt-outs.destroy', $optOut->id))
        ->assertSessionHas('success');

    expect(OptOut::withoutTenancy()->whereKey($optOut->id)->exists())->toBeFalse();
});

it('keeps one company opt-out list out of another', function () {
    $other = Tenant::withoutGlobalScopes()->where('slug', 'kilimanjaro-suppliers')->firstOrFail();

    app(TenantManager::class)->set($other);
    OptOut::create(['dest_addr' => '+255719999999', 'source' => 'manual']);
    app(TenantManager::class)->forget();

    test()->actingAs(tplAdmin())
        ->get(route('admin.sms.opt-outs.index'))
        ->assertOK()
        ->assertDontSee('+255719999999');

    OptOut::withoutTenancy()->where('tenant_id', $other->id)->forceDelete();
});

it('exports the opt-out list as csv', function () {
    test()->actingAs(tplAdmin())->post(route('admin.sms.opt-outs.store'), ['dest_addr' => '0712345678']);

    $response = test()->actingAs(tplAdmin())->get(route('admin.sms.opt-outs.export'));

    $response->assertOK();

    expect($response->streamedContent())->toContain('+255712345678');
});
