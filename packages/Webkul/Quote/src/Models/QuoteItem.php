<?php

namespace Webkul\Quote\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Quote\Contracts\QuoteItem as QuoteItemContract;

class QuoteItem extends Model implements QuoteItemContract
{
    protected $table = 'quote_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sku',
        'name',
        'quantity',
        'price',
        'coupon_code',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'tax_amount',
        'total',
        'product_id',
        // AZIMIO 9.4.2: a quote line may be a service instead of a product.
        // Populated by the hook in ServicesServiceProvider, which unpacks
        // the `svc-12` reference the quote form's lookup submits.
        'service_id',
        'quote_id',
    ];

    /**
     * AZIMIO 9.4.2: the service this line sells, when it sells one.
     */
    public function service()
    {
        return $this->belongsTo(\Azimio\Services\Models\Service::class);
    }

    /**
     * Get the quote record associated with the quote item.
     */
    public function quote()
    {
        return $this->belongsTo(QuoteProxy::modelClass());
    }
}
