<?php

namespace Azimio\Messaging;

use Azimio\Messaging\Contracts\SmsDriver;
use Azimio\Messaging\Drivers\AfricasTalkingDriver;
use Azimio\Messaging\Drivers\BeemDriver;
use Azimio\Messaging\Drivers\LogDriver;
use Azimio\Messaging\Exceptions\DriverNotSupported;
use Azimio\Messaging\Models\SenderProfile;
use Closure;

/**
 * Builds a driver for a given sender profile.
 *
 * Drivers are NOT singletons: each one carries one profile's credentials,
 * so caching an instance across tenants would send one tenant's messages
 * through another's account. A fresh instance per resolution is the whole
 * point.
 *
 * Credentials come from the profile (encrypted at rest, decision 8.0.5) and
 * fall back to the platform config block for the AZIMIOCRM profile.
 */
class DriverManager
{
    /** @var array<string, Closure> */
    private array $custom = [];

    /**
     * Register an additional driver at runtime, for tests or a future
     * provider that does not warrant a class here yet.
     */
    public function extend(string $name, Closure $factory): void
    {
        $this->custom[$name] = $factory;
    }

    /**
     * Resolve the driver a profile sends through.
     */
    public function for(SenderProfile $profile): SmsDriver
    {
        return $this->make($profile->driver, $this->credentialsFor($profile));
    }

    /**
     * Resolve by driver name with explicit config — used for the platform
     * account and for connection tests before a profile is saved (8.6).
     */
    public function make(string $driver, array $config = []): SmsDriver
    {
        if (isset($this->custom[$driver])) {
            return ($this->custom[$driver])($config);
        }

        $config = array_merge(
            (array) config("messaging.drivers.{$driver}", []),
            $config,
        );

        return match ($driver) {
            'log'            => new LogDriver($config),
            'beem'           => new BeemDriver($config),
            'africastalking' => new AfricasTalkingDriver($config),
            default          => throw DriverNotSupported::named($driver),
        };
    }

    /**
     * The driver used when nothing names one.
     */
    public function default(): SmsDriver
    {
        return $this->make((string) config('messaging.default', 'log'));
    }

    /**
     * A profile's own credentials win; the platform profile falls back to
     * the platform config block, which is where its keys actually live.
     */
    private function credentialsFor(SenderProfile $profile): array
    {
        $credentials = (array) ($profile->credentials ?? []);

        if ($profile->isPlatform()) {
            $credentials = array_merge(
                (array) config('messaging.drivers.'.$profile->driver, []),
                $credentials,
            );
        }

        return array_filter($credentials, fn ($value) => $value !== null && $value !== '');
    }
}
