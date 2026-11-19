<?php

use Azimio\Messaging\Http\Controllers\Admin\BulkController;
use Azimio\Messaging\Http\Controllers\Admin\ComposeController;
use Azimio\Messaging\Http\Controllers\Admin\CreditRequestController;
use Azimio\Messaging\Http\Controllers\Admin\MessageLogController;
use Azimio\Messaging\Http\Controllers\Admin\OptOutController;
use Azimio\Messaging\Http\Controllers\Admin\SmsSettingsController;
use Azimio\Messaging\Http\Controllers\Admin\TemplateController;
use Illuminate\Support\Facades\Route;

/**
 * Tenant-facing SMS routes.
 *
 * Mounted by MessagingServiceProvider under the admin path with
 * ['web', 'user', 'tenant', 'admin_locale'] — tenant context is already
 * established before any route here runs, and the 'user' middleware
 * applies the ACL keys declared in Admin's acl.php.
 *
 * Remaining items: 8.7 compose, 8.8 bulk, 8.9 templates, 8.10 log,
 * 8.11 opt-outs, 8.13 credit requests.
 */
Route::prefix('sms')->group(function () {
    Route::get('settings', [SmsSettingsController::class, 'index'])
        ->name('admin.sms.settings.index');

    Route::post('settings', [SmsSettingsController::class, 'store'])
        ->name('admin.sms.settings.store');

    Route::post('settings/platform', [SmsSettingsController::class, 'usePlatform'])
        ->name('admin.sms.settings.platform');

    Route::post('settings/test', [SmsSettingsController::class, 'test'])
        ->name('admin.sms.settings.test');

    // Compose a single message (8.7).
    Route::get('compose', [ComposeController::class, 'index'])
        ->name('admin.sms.compose.index');

    // Authoritative segment/credit count for the live counter — the page
    // deliberately does not compute this itself.
    Route::post('compose/preview', [ComposeController::class, 'preview'])
        ->name('admin.sms.compose.preview');

    Route::post('compose', [ComposeController::class, 'send'])
        ->name('admin.sms.compose.send');

    // Message log and batch history (8.10).
    Route::get('log', [MessageLogController::class, 'index'])
        ->name('admin.sms.log.index');

    Route::get('log/export', [MessageLogController::class, 'export'])
        ->name('admin.sms.log.export');

    Route::get('batches', [MessageLogController::class, 'batches'])
        ->name('admin.sms.batches.index');

    Route::get('batches/{id}', [MessageLogController::class, 'show'])
        ->name('admin.sms.batches.show');

    Route::post('batches/{id}/retry', [MessageLogController::class, 'retry'])
        ->name('admin.sms.batches.retry');

    // Bulk send (8.8). Compose, confirm, then send — nothing is written or
    // reserved until the final POST.
    Route::get('bulk', [BulkController::class, 'index'])
        ->name('admin.sms.bulk.index');

    Route::post('bulk/from-selection', [BulkController::class, 'fromSelection'])
        ->name('admin.sms.bulk.from_selection');

    Route::post('bulk/confirm', [BulkController::class, 'confirm'])
        ->name('admin.sms.bulk.confirm');

    Route::post('bulk/send', [BulkController::class, 'send'])
        ->name('admin.sms.bulk.send');

    // Templates (8.9).
    Route::get('templates', [TemplateController::class, 'index'])
        ->name('admin.sms.templates.index');

    Route::get('templates/create', [TemplateController::class, 'create'])
        ->name('admin.sms.templates.create');

    Route::post('templates', [TemplateController::class, 'store'])
        ->name('admin.sms.templates.store');

    Route::post('templates/preview', [TemplateController::class, 'preview'])
        ->name('admin.sms.templates.preview');

    Route::get('templates/{id}/edit', [TemplateController::class, 'edit'])
        ->name('admin.sms.templates.edit');

    Route::put('templates/{id}', [TemplateController::class, 'update'])
        ->name('admin.sms.templates.update');

    Route::delete('templates/{id}', [TemplateController::class, 'destroy'])
        ->name('admin.sms.templates.destroy');

    // Opt-outs (8.11).
    Route::get('opt-outs', [OptOutController::class, 'index'])
        ->name('admin.sms.opt-outs.index');

    Route::post('opt-outs', [OptOutController::class, 'store'])
        ->name('admin.sms.opt-outs.store');

    Route::post('opt-outs/import', [OptOutController::class, 'import'])
        ->name('admin.sms.opt-outs.import');

    Route::get('opt-outs/export', [OptOutController::class, 'export'])
        ->name('admin.sms.opt-outs.export');

    Route::delete('opt-outs/{id}', [OptOutController::class, 'destroy'])
        ->name('admin.sms.opt-outs.destroy');

    // Credit balance and requests (8.13).
    Route::get('credits', [CreditRequestController::class, 'index'])
        ->name('admin.sms.credits.index');

    Route::post('credits', [CreditRequestController::class, 'store'])
        ->name('admin.sms.credits.store');
});
