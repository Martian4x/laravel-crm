<?php

use Azimio\Messaging\Http\Controllers\Admin\ComposeController;
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
});
