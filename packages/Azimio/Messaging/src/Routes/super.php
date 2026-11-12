<?php

use Azimio\Messaging\Http\Controllers\Super\PlatformSmsController;
use Illuminate\Support\Facades\Route;

/**
 * Platform-admin SMS routes.
 *
 * Mounted under <admin_path>/super with
 * ['web', 'user', 'super_admin', 'admin_locale'] — EnsureSuperAdmin returns
 * 404 for tenant users, matching the existing super panel.
 *
 * Remaining: 8.16 credit request queue, 8.17 (pending a decision),
 * 8.18 kill switch and failure-rate alerting.
 */
Route::prefix('sms')->group(function () {
    Route::get('/', [PlatformSmsController::class, 'index'])->name('super.sms.index');

    Route::post('/', [PlatformSmsController::class, 'store'])->name('super.sms.store');

    Route::post('allocate', [PlatformSmsController::class, 'allocate'])->name('super.sms.allocate');

    Route::get('messages', [PlatformSmsController::class, 'messages'])->name('super.sms.messages');

    Route::get('{id}/ledger', [PlatformSmsController::class, 'ledger'])->name('super.sms.ledger');

    Route::post('{id}/reconcile', [PlatformSmsController::class, 'reconcile'])->name('super.sms.reconcile');
});
