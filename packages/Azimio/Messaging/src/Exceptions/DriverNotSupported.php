<?php

namespace Azimio\Messaging\Exceptions;

class DriverNotSupported extends MessagingException
{
    public static function named(string $driver): self
    {
        return new self("SMS driver [{$driver}] is not supported.");
    }

    public static function notImplemented(string $driver): self
    {
        return new self("SMS driver [{$driver}] is not implemented yet.");
    }
}
