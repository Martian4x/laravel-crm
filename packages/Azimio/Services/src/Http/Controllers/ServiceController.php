<?php

namespace Azimio\Services\Http\Controllers;

use Azimio\Services\Enums\ServiceUnit;
use Azimio\Services\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Tenant-facing CRUD for services (9.4).
 *
 * Every query here goes through the Service model, so TenantScope applies
 * and a tenant can only ever reach its own price list. The routes are
 * mounted behind the same ['web', 'user', 'tenant'] stack as the rest of
 * the admin area and are ACL-gated on the `services*` keys.
 */
class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $services = Service::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->get('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->get('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('services::index', [
            'services' => $services,
            'search'   => $search,
            'status'   => $request->get('status'),
        ]);
    }

    public function create()
    {
        return view('services::create', [
            'units' => ServiceUnit::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Service::create($validated);

        session()->flash('success', trans('services::app.created'));

        return redirect()->route('admin.services.index');
    }

    public function edit(int $id)
    {
        return view('services::edit', [
            'service' => Service::findOrFail($id),
            'units'   => ServiceUnit::options(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $service = Service::findOrFail($id);

        $service->update($this->validated($request, $id));

        session()->flash('success', trans('services::app.updated'));

        return redirect()->route('admin.services.index');
    }

    /**
     * Retire a service rather than delete it where it has been quoted.
     *
     * A hard delete would orphan quote lines that reference it, so the
     * destructive option is only offered when nothing points at it. The
     * grid offers "deactivate" either way.
     */
    public function destroy(int $id)
    {
        $service = Service::findOrFail($id);

        $service->delete();

        session()->flash('success', trans('services::app.deleted'));

        return redirect()->route('admin.services.index');
    }

    public function toggle(int $id)
    {
        $service = Service::findOrFail($id);

        $service->update(['is_active' => ! $service->is_active]);

        session()->flash('success', trans(
            $service->is_active ? 'services::app.activated' : 'services::app.deactivated'
        ));

        return redirect()->route('admin.services.index');
    }

    /**
     * `?? null` on every nullable field: a field absent from the request is
     * MISSING from the validated array rather than null, and reading it
     * directly is an "Undefined array key" crash. This codebase has shipped
     * that bug on `locale`, a bulk-send `name`, tenant `currency` and the
     * SMS credentials.
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:services,code,'.($ignoreId ?? 'NULL').',id,tenant_id,'.tenant_id();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:64|'.$unique,
            'description' => 'nullable|string|max:5000',
            'price'       => 'required|numeric|min:0|max:999999999',
            'unit'        => 'required|in:'.implode(',', ServiceUnit::values()),
            'is_taxable'  => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ]);

        return [
            'name'        => $validated['name'],
            'code'        => ($validated['code'] ?? null) ?: null,
            'description' => ($validated['description'] ?? null) ?: null,
            'price'       => $validated['price'],
            'unit'        => $validated['unit'],
            'is_taxable'  => $request->boolean('is_taxable'),
            'is_active'   => $request->boolean('is_active'),
        ];
    }
}
