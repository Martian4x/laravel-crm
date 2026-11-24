<?php

namespace Azimio\Services\Enums;

/**
 * How a service is billed (9.4.1).
 *
 * `Fixed` is the default because a flat price per job is the commonest
 * arrangement, and because it is the only unit that needs no explanation on
 * a quote.
 */
enum ServiceUnit: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Month = 'month';
    case Fixed = 'fixed';

    public function translationKey(): string
    {
        return 'services::app.units.'.$this->value;
    }

    public function label(): string
    {
        return (string) trans($this->translationKey());
    }

    /**
     * @return array<string, string> value => translated label
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
