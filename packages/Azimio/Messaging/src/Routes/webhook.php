<?php

/**
 * Public provider callback routes.
 *
 * No session, no auth: these are called by the SMS provider. Every route
 * here MUST verify the request signature before trusting anything, and MUST
 * resolve tenant context from the sender profile the message was sent
 * under — never from the request body, which the caller controls.
 *
 * Populated by Phase 8 item 8.19:
 *   POST delivery-report  provider delivery status callback
 *   POST inbound          two-way / inbound SMS, STOP + ACHA opt-out
 */
