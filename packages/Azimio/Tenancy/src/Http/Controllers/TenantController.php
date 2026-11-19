<?php

namespace Azimio\Tenancy\Http\Controllers;

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantEraser;
use Azimio\Tenancy\TenantExporter;
use Azimio\Tenancy\TenantOnboarder;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class TenantController extends Controller
{
    /**
     * List all tenants with usage counts.
     */
    public function index()
    {
        $tenants = Tenant::query()
            ->orderBy('id')
            ->get()
            ->map(function ($tenant) {
                $tenant->users_count = DB::table('users')->where('tenant_id', $tenant->id)->count();
                $tenant->leads_count = DB::table('leads')->where('tenant_id', $tenant->id)->count();
                $tenant->persons_count = DB::table('persons')->where('tenant_id', $tenant->id)->count();

                return $tenant;
            });

        return view('azimio::tenants.index', compact('tenants'));
    }

    /**
     * Create a tenant with its admin user.
     */
    public function store(TenantOnboarder $onboarder)
    {
        $validated = request()->validate([
            'name'           => 'required|string|max:255',
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|unique:users,email',
            'admin_password' => 'required|min:6',
            'locale'         => 'required|in:'.implode(',', array_keys(config('app.available_locales'))),
        ]);

        $onboarder->onboard(
            [
                'name'   => $validated['name'],
                'locale' => $validated['locale'],
            ],
            [
                'name'     => $validated['admin_name'],
                'email'    => $validated['admin_email'],
                'password' => $validated['admin_password'],
            ]
        );

        session()->flash('success', trans('azimio::app.tenants.created'));

        return redirect()->route('super.tenants.index');
    }

    /**
     * Suspend or reactivate a tenant.
     */
    public function toggleStatus(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $suspending = $tenant->isActive();

        /**
         * 7.9: record WHY. A bare boolean leaves the next admin — and the
         * tenant on the phone asking — unable to tell non-payment from
         * abuse from a mis-click. Reactivating clears it, so a stale
         * reason never hangs around on a working account.
         */
        $tenant->forceFill([
            'status'            => $suspending ? Tenant::STATUS_SUSPENDED : Tenant::STATUS_ACTIVE,
            'suspended_at'      => $suspending ? now() : null,
            'suspension_reason' => $suspending
                ? (request()->input('reason') ?: trans('azimio::app.tenants.no-reason-given'))
                : null,
        ])->save();

        session()->flash('success', trans(
            $tenant->isActive() ? 'azimio::app.tenants.activated' : 'azimio::app.tenants.suspended'
        ));

        return redirect()->route('super.tenants.index');
    }

    /**
     * Show the edit form for a tenant.
     */
    public function edit(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $eraser = app(TenantEraser::class);

        return view('azimio::tenants.edit', [
            'tenant'        => $tenant,
            'locales'       => config('app.available_locales'),
            'deleteBlocked' => $eraser->blockedReason($tenant),
            'graceDays'     => $eraser->graceDays(),
        ]);
    }

    /**
     * Update a tenant's editable settings.
     */
    public function update(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = request()->validate([
            'name'      => 'required|string|max:255',
            'locale'    => 'required|in:'.implode(',', array_keys(config('app.available_locales'))),
            'currency'  => 'nullable|string|max:10',
            'plan'      => 'nullable|string|max:50',
            'max_users' => 'nullable|integer|min:1|max:9999',
            // 7.9: trial end is editable, so sales can extend one.
            'trial_ends_at' => 'nullable|date',
        ]);

        $tenant->update([
            'name'      => $validated['name'],
            'locale'    => $validated['locale'],
            // These three are `nullable`, so Laravel omits the key when the
            // field is not sent and `?:` alone raises "Undefined array key".
            // Same trap as the locale bug in 260f6429 and the batch-name one
            // in a9ca5baa; `?? null` first, then `?:` to keep the existing
            // empty-string-means-null behaviour.
            'currency'  => ($validated['currency'] ?? null) ?: null,
            'plan'      => ($validated['plan'] ?? null) ?: null,
            'max_users' => ($validated['max_users'] ?? null) ?: null,
        ]);

        // Not mass-assignable, and a blank field means "no trial" rather
        // than "leave it alone" — this is the screen where that is set.
        $tenant->forceFill([
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
        ])->save();

        session()->flash('success', trans('azimio::app.tenants.updated'));

        return redirect()->route('super.tenants.index');
    }

    /**
     * Delete a tenant and all its users.
     */
    public function destroy(int $id, TenantEraser $eraser)
    {
        $tenant = Tenant::findOrFail($id);

        /**
         * 7.13: suspend -> export -> delete, enforced rather than
         * suggested. Previously this deleted immediately, which meant one
         * mis-click destroyed a customer's data with no window to undo it.
         *
         * The guard refuses while the tenant is still active, and while the
         * grace period is still running.
         */
        if ($reason = $eraser->blockedReason($tenant)) {
            session()->flash('error', $reason);

            return redirect()->route('super.tenants.edit', $tenant->id);
        }

        /**
         * Typing the company name is the confirmation. A yes/no dialog is
         * too easy to click through for something this final.
         */
        if (request()->input('confirm_name') !== $tenant->name) {
            session()->flash('error', trans('azimio::app.tenants.delete-name-mismatch'));

            return redirect()->route('super.tenants.edit', $tenant->id);
        }

        $name = $tenant->name;

        $removed = $eraser->erase($tenant);

        session()->flash('success', trans('azimio::app.tenants.deleted-with-counts', [
            'name' => $name,
            'rows' => array_sum($removed),
        ]));

        return redirect()->route('super.tenants.index');
    }

    /**
     * Download everything one tenant owns, as a zip of CSVs (7.12).
     *
     * Streamed and then deleted: the archive is a full copy of one
     * customer's data and has no business sitting in storage.
     */
    public function export(int $id, TenantExporter $exporter)
    {
        $tenant = Tenant::findOrFail($id);

        $path = $exporter->export($tenant);

        return response()->download(
            $path,
            'azimio-export-'.$tenant->slug.'-'.now()->format('Y-m-d').'.zip',
        )->deleteFileAfterSend(true);
    }

    /**
     * Log in as the tenant's first admin user. The super admin's id is kept
     * in the session so they can return via the revert route.
     */
    public function impersonate(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $target = User::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if (! $target) {
            session()->flash('error', trans('azimio::app.tenants.no-user'));

            return redirect()->route('super.tenants.index');
        }

        session()->put('azimio_impersonator_id', auth()->guard('user')->id());

        auth()->guard('user')->login($target);

        return redirect()->route('admin.dashboard.index');
    }

    /**
     * Return from an impersonated session to the original super admin.
     */
    public function revert()
    {
        $impersonatorId = session()->pull('azimio_impersonator_id');

        if (! $impersonatorId) {
            abort(404);
        }

        $superAdmin = User::withoutTenancy()
            ->where('id', $impersonatorId)
            ->where('is_super_admin', 1)
            ->first();

        if (! $superAdmin) {
            abort(404);
        }

        auth()->guard('user')->login($superAdmin);

        return redirect()->route('super.tenants.index');
    }
}
