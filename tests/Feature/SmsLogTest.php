<?php

use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Azimio\Tenancy\Models\Tenant;
use Webkul\User\Models\User;

/**
 * Phase 8.10 — message log and batch history.
 *
 * These render the real pages rather than only compiling the templates:
 * 8.7 shipped a Blade error that compileString did not catch and only an
 * actual page load surfaced.
 */
function logAdmin(): User
{
    return User::withoutTenancy()->where('email', 'admin@twiga-traders.test')->firstOrFail();
}

function logTenant(): Tenant
{
    return Tenant::withoutGlobalScopes()->where('slug', 'twiga-traders')->firstOrFail();
}

afterEach(function () {
    Message::withoutTenancy()->where('tenant_id', logTenant()->id)->forceDelete();
    Batch::withoutTenancy()->where('tenant_id', logTenant()->id)->forceDelete();
});

function makeLoggedBatch(string $status = 'failed'): Batch
{
    $batch = new Batch;

    $batch->forceFill([
        'tenant_id'        => logTenant()->id,
        'name'             => 'Log Test Batch',
        'body'             => 'Habari',
        'total_recipients' => 1,
        'failed_count'     => $status === 'failed' ? 1 : 0,
        'status'           => 'partially_failed',
    ])->save();

    $message = new Message;

    $message->forceFill([
        'tenant_id'     => logTenant()->id,
        'batch_id'      => $batch->id,
        'dest_addr'     => '+255712345678',
        'body'          => 'Habari',
        'segments'      => 1,
        'status'        => $status,
        'error_message' => $status === 'failed' ? 'Provider refused' : null,
    ])->save();

    return $batch;
}

it('renders the message log', function () {
    makeLoggedBatch();

    test()->actingAs(logAdmin())
        ->get(route('admin.sms.log.index'))
        ->assertOK()
        ->assertSee('+255712345678')
        ->assertSee('Provider refused');
});

it('renders the batch list', function () {
    makeLoggedBatch();

    test()->actingAs(logAdmin())
        ->get(route('admin.sms.batches.index'))
        ->assertOK()
        ->assertSee('Log Test Batch');
});

it('renders a batch detail page with the retry offer', function () {
    $batch = makeLoggedBatch();

    test()->actingAs(logAdmin())
        ->get(route('admin.sms.batches.show', $batch->id))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.log.retry-same'));
});

it('filters the log by status', function () {
    makeLoggedBatch('delivered');

    test()->actingAs(logAdmin())
        ->get(route('admin.sms.log.index', ['status' => 'failed']))
        ->assertOK()
        ->assertSee(trans('messaging::app.sms.log.empty'));
});

it('exports the log as csv', function () {
    makeLoggedBatch();

    $response = test()->actingAs(logAdmin())->get(route('admin.sms.log.export'));

    $response->assertOK()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('+255712345678');
});

it('does not serve the log to a guest', function () {
    test()->get(route('admin.sms.log.index'))->assertRedirect();
});
