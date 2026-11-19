<?php

use Azimio\Messaging\Http\Controllers\Webhook\InboundController;
use Illuminate\Support\Facades\Route;

/**
 * Public provider callback routes.
 *
 * No session, no auth: these are called by the SMS provider.
 *
 * IMPORTANT — corrected in 8.3 after reading Beem's documentation:
 *
 *  - There is NO delivery-report callback for Beem's REST SMS API. Delivery
 *    status is POLLED by azimio:sms:poll-delivery-reports (8.19), so no
 *    delivery-report route belongs here at all.
 *  - Inbound/two-way SMS IS a callback, but Beem documents NO signature or
 *    HMAC scheme for it — only an unspecified "optionally validate the API
 *    key". An inbound callback therefore CANNOT be proven to come from
 *    Beem, which is why this whole group is disabled by default via
 *    messaging.webhooks.enabled and the controller treats every payload as
 *    hostile input.
 *
 * Ask Beem for a real verification method before enabling this in
 * production, and pair it with an unguessable path and whatever network
 * restriction the deployment can manage.
 */
Route::post('inbound', InboundController::class)->name('messaging.webhook.inbound');
