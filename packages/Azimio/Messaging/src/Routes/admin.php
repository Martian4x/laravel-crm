<?php

/**
 * Tenant-facing SMS routes.
 *
 * Mounted by MessagingServiceProvider under the admin path with
 * ['web', 'user', 'tenant', 'admin_locale'] — tenant context is already
 * established before any route here runs.
 *
 * Populated by Phase 8 items:
 *   8.6  settings (own provider setup / platform mode)
 *   8.7  compose + send single
 *   8.8  bulk send
 *   8.9  templates CRUD
 *   8.10 message log + batch history
 *   8.11 opt-out management
 *   8.13 credit request + balance
 */
