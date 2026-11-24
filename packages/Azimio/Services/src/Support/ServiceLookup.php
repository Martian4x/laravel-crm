<?php

namespace Azimio\Services\Support;

use Azimio\Services\Models\Service;

/**
 * Services, shaped like products, for the quote form's lookup (9.4.2).
 *
 * The lookup sends `query=` and reads `response.data.data`, then puts the
 * chosen row's `id` straight into the line's `product_id`. So a service row
 * carries a NAMESPACED id — `svc-12` — which QuoteLineRef unpacks on the
 * way back in. That is what lets services be sellable without rewriting the
 * upstream Vue component to carry a second identifier.
 *
 * The shape below deliberately matches ProductResource field for field.
 * The lookup renders `name`; the quote form's addProduct() reads `name`,
 * `price` and `quantity`.
 */
class ServiceLookup
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function matching(?string $term, int $limit = 20): array
    {
        $term = trim((string) $term);

        // Tenant-scoped by TenantScope, and active-only: a retired service
        // stays on the quotes that already sell it but must not be
        // offered on a new one.
        $services = Service::query()
            ->active()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $services->map(fn (Service $service) => [
            'id'          => QuoteLineRef::forService($service->id),
            // Labelled so a seller can tell a service apart from a product
            // of the same name in a single flat result list.
            'name'        => $service->name.' ('.trans('services::app.lookup-tag').')',
            'description' => $service->description,
            'sku'         => $service->code,
            'price'       => $service->price,
            // Services default to one unit; the seller edits the quantity
            // on the line, exactly as for a product.
            'quantity'    => 1,
            'created_at'  => $service->created_at,
            'updated_at'  => $service->updated_at,
        ])->all();
    }
}
