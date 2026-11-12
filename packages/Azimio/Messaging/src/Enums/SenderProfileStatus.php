<?php

namespace Azimio\Messaging\Enums;

/**
 * Approval state of a sender name (sms_sender_profiles.status).
 *
 * A sender name must be registered with the mobile operators before it will
 * deliver, so a profile cannot send until a platform admin approves it
 * (8.17).
 */
enum SenderProfileStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disabled = 'disabled';

    public function canSend(): bool
    {
        return $this === self::Approved;
    }
}
