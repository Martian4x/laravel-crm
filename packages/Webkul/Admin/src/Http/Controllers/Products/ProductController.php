<?php

namespace Webkul\Admin\Http\Controllers\Products;

use Azimio\Services\Support\ServiceLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Product\ProductDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\ProductResource;
use Webkul\Product\Repositories\ProductRepository;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ProductRepository $productRepository)
    {
        request()->request->add(['entity_type' => 'products']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ProductDataGrid::class)->process();
        }

        return view('admin::products.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::products.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(AttributeForm $request)
    {
        Event::dispatch('product.create.before');

        $product = $this->productRepository->create($request->all());

        Event::dispatch('product.create.after', $product);

        session()->flash('success', trans('admin::app.products.index.create-success'));

        return redirect()->route('admin.products.index');
    }

    /**
     * Show the form for viewing the specified resource.
     */
    public function view(int $id): View
    {
        $product = $this->productRepository->findOrFail($id);

        return view('admin::products.view', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        $inventories = $product->inventories()
            ->with('location')
            ->get()
            ->map(function ($inventory) {
                return [
                    'id'                    => $inventory->id,
                    'name'                  => $inventory->location->name,
                    'warehouse_id'          => $inventory->warehouse_id,
                    'warehouse_location_id' => $inventory->warehouse_location_id,
                    'in_stock'              => $inventory->in_stock,
                    'allocated'             => $inventory->allocated,
                ];
            });

        return view('admin::products.edit', compact('product', 'inventories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id)
    {
        Event::dispatch('product.update.before', $id);

        $product = $this->productRepository->update($request->all(), $id);

        Event::dispatch('product.update.after', $product);

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.products.index.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.products.index.update-success'));

        return redirect()->route('admin.products.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeInventories(int $id, ?int $warehouseId = null): JsonResponse
    {
        $this->validate(request(), [
            'inventories'                         => 'array',
            'inventories.*.warehouse_location_id' => 'required',
            'inventories.*.warehouse_id'          => 'required',
            'inventories.*.in_stock'              => 'required|integer|min:0',
            'inventories.*.allocated'             => 'required|integer|min:0',
        ]);

        $product = $this->productRepository->findOrFail($id);

        Event::dispatch('product.update.before', $id);

        $this->productRepository->saveInventories(request()->all(), $id, $warehouseId);

        Event::dispatch('product.update.after', $product);

        return new JsonResponse([
            'message' => trans('admin::app.products.index.update-success'),
        ], 200);
    }

    /**
     * Search product results
     */
    public function search(): JsonResource
    {
        $products = $this->productRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->orderBy('created_at', 'desc')
            ->get();

        /**
         * AZIMIO 9.4.2: services are sellable on quotes, and this is the
         * endpoint the quote form's lookup reads.
         *
         * They are merged in here rather than given their own picker
         * because the lookup puts the chosen row's `id` directly into the
         * line's `product_id` — its hidden input is
         * `v-model="selectedItem.id"` and there is no second field to use.
         * Service rows therefore carry a namespaced id (`svc-12`), which
         * ProductOrService validates and the hook in ServicesServiceProvider
         * unpacks before the row is written.
         *
         * Products stay first: they are the commoner case, and merging
         * rather than replacing means nothing about product search changes.
         */
        $results = array_merge(
            ProductResource::collection($products)->resolve(),
            ServiceLookup::matching(request('query')),
        );

        return JsonResource::collection($results);
    }

    /**
     * Returns product inventories grouped by warehouse.
     */
    public function warehouses(int $id): JsonResponse
    {
        $warehouses = $this->productRepository->getInventoriesGroupedByWarehouse($id);

        return response()->json(array_values($warehouses));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);

        try {
            Event::dispatch('settings.products.delete.before', $id);

            $product->delete($id);

            Event::dispatch('settings.products.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.products.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('product.delete.before', $index);

            $this->productRepository->delete($index);

            Event::dispatch('product.delete.after', $index);
        }

        return new JsonResponse([
            'message' => trans('admin::app.products.index.delete-success'),
        ]);
    }
}
