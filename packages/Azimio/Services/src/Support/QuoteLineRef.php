<?php

namespace Azimio\Services\Support;

use Azimio\Services\Models\Service;
use Webkul\Product\Models\Product;

/**
 * What a quote line's `product_id` field is pointing at (9.4.2).
 *
 * The quote form's lookup component submits whatever `id` the search
 * endpoint returned, straight into `product_id` — the hidden input is
 * `v-model="selectedItem.id"` and there is no second field to put anything
 * in. Rather than rewrite upstream Vue to add one, services come back from
 * the search with a NAMESPACED id, `svc-12`, and are unpacked here.
 *
 * Both the validation rule and the save-time translation go through this
 * one class on purpose: a rule that accepts a reference the writer then
 * fails to understand (or the reverse) is how a line silently lands with
 * neither a product nor a service attached.
 */
class QuoteLineRef
{
    public const SERVICE_PREFIX = 'svc-';

    private function __construct(
        public readonly ?int $productId,
        public readonly ?int $serviceId,
    ) {}

    /**
     * Parse a submitted reference. Returns null when it is neither a known
     * product nor a usable service — the caller decides what to do about
     * that (reject, in both cases).
     */
    public static function parse(mixed $value): ?self
    {
        if (is_string($value) && str_starts_with($value, self::SERVICE_PREFIX)) {
            $id = (int) substr($value, strlen(self::SERVICE_PREFIX));

            // Scoped by TenantScope, so this cannot resolve another
            // tenant's service even if the id is guessed.
            $service = Service::query()->whereKey($id)->first();

            // A retired service must not be sellable on a NEW quote. Lines
            // already saved against it keep working -- that is why the
            // module retires instead of deleting.
            if (! $service || ! $service->is_active) {
                return null;
            }

            return new self(null, $service->id);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $product = Product::query()->whereKey((int) $value)->first();

        return $product ? new self($product->id, null) : null;
    }

    public static function forService(int $id): string
    {
        return self::SERVICE_PREFIX.$id;
    }

    public function isService(): bool
    {
        return $this->serviceId !== null;
    }
}
