<?php

namespace Azimio\Messaging\Enums;

/**
 * Why a number could not be turned into a sendable E.164 address.
 *
 * A rejected number is recorded on the message row with its reason rather
 * than silently dropped: "we did not send to 47 of your 500 contacts" is
 * only actionable if the user can see which ones and why.
 */
enum PhoneRejection: string
{
    case Empty = 'empty';
    case TooShort = 'too_short';
    case TooLong = 'too_long';
    case NotMobile = 'not_mobile';
    case Unrecognised = 'unrecognised';

    /**
     * Translation key for the message shown beside the offending number.
     */
    public function translationKey(): string
    {
        return 'messaging::app.sms.phone.'.str_replace('_', '-', $this->value);
    }
}
