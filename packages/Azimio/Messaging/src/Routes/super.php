<?php

/**
 * Platform-admin SMS routes.
 *
 * Mounted under <admin_path>/super with
 * ['web', 'user', 'super_admin', 'admin_locale'] — EnsureSuperAdmin returns
 * 404 for tenant users, matching the existing super panel.
 *
 * Populated by Phase 8 items:
 *   8.14 platform sender profile + provider credentials
 *   8.15 credit allocation + ledger + reconcile
 *   8.16 credit request queue
 *   8.17 sender-name approval
 *   8.18 cross-tenant log, usage, kill switch
 */
