<?php

namespace Webkul\Quote\Repositories;

use Azimio\Services\Models\Service;
use Azimio\Services\Support\QuoteLineRef;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Webkul\Core\Eloquent\Repository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Quote\Contracts\QuoteItem;

class QuoteItemRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Quote\Contracts\QuoteItem';
    }

    /**
     * @return mixed
     */
    public function create(array $data)
    {
        if (empty($data['product_id'])) {
            return null;
        }

        // AZIMIO 9.4.2: the line may be a service.
        if ($service = $this->resolveService($data['product_id'])) {
            return parent::create(array_merge($data, [
                'product_id' => null,
                'service_id' => $service->id,
                'sku'        => $service->code,
                'name'       => $service->name,
            ]));
        }

        $product = $this->productRepository->findOrFail($data['product_id']);

        $quoteItem = parent::create(array_merge($data, [
            'sku'  => $product->sku,
            'name' => $product->name,
        ]));

        return $quoteItem;
    }

    /**
     * @param  int  $id
     * @param  string  $attribute
     * @return QuoteItem
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        // AZIMIO 9.4.2: the line may be a service.
        if ($service = $this->resolveService($data['product_id'] ?? null)) {
            return parent::update(array_merge($data, [
                'product_id' => null,
                'service_id' => $service->id,
                'sku'        => $service->code,
                'name'       => $service->name,
            ]), $id);
        }

        $product = $this->productRepository->findOrFail($data['product_id']);

        $quoteItem = parent::update(array_merge($data, [
            'sku'  => $product->sku,
            'name' => $product->name,
        ]), $id);

        return $quoteItem;
    }

    /**
     * AZIMIO 9.4.2: unpack a `svc-12` line reference into a service.
     *
     * The quote form's lookup writes whatever id the search returned
     * straight into `product_id` — its hidden input is
     * `v-model="selectedItem.id"` and there is no second field to use — so
     * a service arrives here namespaced. Resolution goes through
     * QuoteLineRef, the same parser the ProductOrService validation rule
     * uses, so a reference cannot pass validation and then be
     * misunderstood here.
     *
     * Returns null for an ordinary product id, leaving the original path
     * below completely untouched.
     */
    private function resolveService(mixed $reference): ?Service
    {
        if (! is_string($reference) || ! str_starts_with($reference, QuoteLineRef::SERVICE_PREFIX)) {
            return null;
        }

        $ref = QuoteLineRef::parse($reference);

        if ($ref === null || ! $ref->isService()) {
            // Validation should already have rejected this. Failing loudly
            // beats writing a line that points at nothing.
            throw (new ModelNotFoundException)->setModel(Service::class, [$reference]);
        }

        return Service::query()->findOrFail($ref->serviceId);
    }
}
