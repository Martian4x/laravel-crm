<?php

use Azimio\Messaging\Http\Controllers\Admin\BulkController;
use Azimio\Messaging\Http\Controllers\Admin\ComposeController;
use Azimio\Messaging\Http\Controllers\Admin\CreditRequestController;
use Azimio\Messaging\Http\Controllers\Admin\MessageLogController;
use Azimio\Messaging\Http\Controllers\Admin\SmsSettingsController;
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

    // Credit balance and requests (8.13).
    Route::get('credits', [CreditRequestController::class, 'index'])
        ->name('admin.sms.credits.index');

    Route::post('credits', [CreditRequestController::class, 'store'])
        ->name('admin.sms.credits.store');
});
