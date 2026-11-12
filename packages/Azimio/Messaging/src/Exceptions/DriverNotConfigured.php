<?php

namespace Azimio\Messaging\Exceptions;

class DriverNotConfigured extends MessagingException
{
    public static function missingCredentials(string $driver, string $field): self
    {
        return new self("SMS driver [{$driver}] is missing required credential [{$field}].");
    }
}
