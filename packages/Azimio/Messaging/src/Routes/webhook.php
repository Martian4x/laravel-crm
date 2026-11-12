<?php

/**
 * Public provider callback routes.
 *
 * No session, no auth: these are called by the SMS provider.
 *
 * IMPORTANT — corrected in 8.3 after reading Beem's documentation:
 *
 *  - There is NO delivery-report callback for Beem's REST SMS API. Delivery
 *    status is POLLED (see 8.19 and BeemDriver::deliveryReports), so no
 *    delivery-report route belongs here at all.
 *  - Inbound/two-way SMS IS a callback, but Beem documents NO signature or
 *    HMAC scheme for it — only an unspecified "optionally validate the API
 *    key". An inbound callback therefore CANNOT be proven to come from Beem.
 *
 * So the inbound route, when it lands, must treat every payload as hostile
 * input: log it before trusting it, process idempotently by transaction_id,
 * and resolve tenant context from the sender profile that owns the receiving
 * number — never from the request body, which the caller controls.
 *
 * The whole group is disabled by default via messaging.webhooks.enabled.
 *
 * Populated by Phase 8 item 8.19:
 *   POST inbound   two-way / inbound SMS, STOP + ACHA opt-out
 */
