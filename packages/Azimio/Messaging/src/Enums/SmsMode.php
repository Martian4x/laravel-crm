<?php

namespace Azimio\Messaging\Enums;

/**
 * Which account pays for a send.
 *
 * Used for both tenants.sms_mode (the tenant's current setting) and
 * sms_messages.cost_source (what actually paid for a given message). They
 * are the same two values on purpose: a message's cost_source is the mode
 * that was active when it went out, which is what makes the ledger and the
 * message log reconcilable.
 *
 * Decision 8.0.4: a tenant is on exactly one mode at a time.
 */
enum SmsMode: string
{
    case Platform = 'platform';
    case Own = 'own';

    /**
     * Platform-mode sends draw on allocated credits; own-provider sends
     * never touch them.
     */
    public function usesPlatformCredits(): bool
    {
        return $this === self::Platform;
    }
}
