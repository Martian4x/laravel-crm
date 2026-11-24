<?php

namespace Azimio\Services\Models;

use Azimio\Services\Enums\ServiceUnit;
use Azimio\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A sellable service (9.4).
 *
 * STRICT tenancy and NO platform defaults: unlike SMS templates, a service
 * is a tenant's own price list. `$sharesPlatformDefaults` is deliberately
 * absent — a NULL-tenant row here would publish one firm's rates to every
 * other firm on the platform.
 */
class Service extends Model
{
    use BelongsToTenant;

    protected $table = 'services';

    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'unit',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'unit'       => ServiceUnit::class,
        'is_taxable' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /**
     * Services a tenant can still put on a new quote.
     *
     * Retired services stay in the table so past quotes remain resolvable,
     * but they must not appear in a picker.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * "150,000.00 / hour" — the price as it reads on a quote line.
     */
    public function priceLabel(): string
    {
        $price = number_format((float) $this->price, 2);

        if ($this->unit === ServiceUnit::Fixed) {
            return $price;
        }

        return $price.' / '.$this->unit->label();
    }
}
