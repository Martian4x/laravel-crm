<?php

namespace Azimio\Services\Rules;

use Azimio\Services\Support\QuoteLineRef;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A quote line may point at a product OR a service (9.4.2).
 *
 * Replaces `exists:products,id` on `items.*.product_id`. That rule is what
 * made services unsellable: the form submits the lookup's chosen id into
 * `product_id`, and anything that was not a product row was rejected before
 * it reached the repository.
 *
 * Resolution goes through QuoteLineRef, which is also what the save-time
 * translation uses, so the two cannot disagree about what a reference means.
 */
class ProductOrService implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (QuoteLineRef::parse($value) === null) {
            $fail('services::app.quote-line-invalid')->translate();
        }
    }
}
