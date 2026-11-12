<?php

namespace Azimio\Messaging\Enums;

/**
 * SMS character encoding, which decides how many characters fit per segment
 * and therefore what a message costs (decision 8.0.1).
 *
 * Swahili uses no characters outside the GSM 03.38 alphabet, so Swahili
 * bodies stay on Gsm7 and cost stays predictable.
 */
enum Encoding: string
{
    case Gsm7 = 'gsm7';
    case Ucs2 = 'ucs2';

    public function singleLimit(): int
    {
        return (int) config('messaging.segments.'.$this->value.'.single');
    }

    public function multipartLimit(): int
    {
        return (int) config('messaging.segments.'.$this->value.'.multipart');
    }
}
