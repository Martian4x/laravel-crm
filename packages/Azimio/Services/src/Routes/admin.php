<?php

use Azimio\Services\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

/**
 * Tenant-facing service routes (9.4).
 *
 * Mounted by ServicesServiceProvider under the admin path with
 * ['web', 'user', 'tenant', 'admin_locale'], so tenant context exists
 * before any of this runs and the `user` middleware applies the
 * `services*` ACL keys declared in Admin's acl.php.
 */
Route::controller(ServiceController::class)->prefix('services')->group(function () {
    Route::get('', 'index')->name('admin.services.index');

    Route::get('create', 'create')->name('admin.services.create');
    Route::post('create', 'store')->name('admin.services.store');

    Route::get('edit/{id}', 'edit')->name('admin.services.edit');
    Route::put('edit/{id}', 'update')->name('admin.services.update');

    // POST rather than DELETE: these are plain form submits, and the admin
    // layout has no XHR delete helper outside the Vue DataGrid.
    Route::post('toggle/{id}', 'toggle')->name('admin.services.toggle');
    Route::post('delete/{id}', 'destroy')->name('admin.services.delete');
});
