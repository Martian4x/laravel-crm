<?php

namespace Azimio\Tenancy\Http\Controllers;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class UserController extends Controller
{
    /**
     * List every user across all tenants. Optional filtering by tenant and a
     * free-text search on name/email. Super admins run unscoped, so the query
     * naturally spans every tenant.
     */
    public function index()
    {
        $query = User::withoutTenancy()
            ->with(['role', 'tenant'])
            ->orderBy('tenant_id')
            ->orderBy('name');

        if ($tenantId = request('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        if ($search = trim((string) request('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20)->withQueryString();

        $tenants = Tenant::orderBy('name')->get();

        return view('azimio::users.index', compact('users', 'tenants'));
    }

    /**
     * Show the create-user form.
     */
    public function create()
    {
        return view('azimio::users.create', [
            'tenants' => Tenant::orderBy('name')->get(),
            'roles'   => $this->rolesForSelect(),
            'locales' => config('app.available_locales'),
        ]);
    }

    /**
     * Persist a new user under the chosen tenant.
     */
    public function store()
    {
        $validated = request()->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|min:6',
            'tenant_id' => 'required|integer|exists:tenants,id',
            'role_id'   => 'required|integer',
            'locale'    => 'nullable|in:'.implode(',', array_keys(config('app.available_locales'))),
            'status'    => 'nullable|boolean',
        ]);

        $this->assertRoleBelongsToTenant($validated['role_id'], $validated['tenant_id']);

        // tenant_id is deliberately not mass-assignable (it is auto-stamped from
        // the active tenant), so set it explicitly via forceFill.
        (new User)->forceFill([
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'tenant_id'       => $validated['tenant_id'],
            'role_id'         => $validated['role_id'],
            'locale'          => $validated['locale'] ?: null,
            'status'          => request()->boolean('status'),
            'view_permission' => 'global',
        ])->save();

        session()->flash('success', trans('azimio::app.users.created'));

        return redirect()->route('super.users.index');
    }

    /**
     * Show the edit-user form.
     */
    public function edit(int $id)
    {
        $user = User::withoutTenancy()->findOrFail($id);

        return view('azimio::users.edit', [
            'user'    => $user,
            'tenants' => Tenant::orderBy('name')->get(),
            'roles'   => $this->rolesForSelect(),
            'locales' => config('app.available_locales'),
        ]);
    }

    /**
     * Update a user's profile, tenant affiliation, role and status.
     */
    public function update(int $id)
    {
        $user = User::withoutTenancy()->findOrFail($id);

        $validated = request()->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,'.$user->id,
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'role_id'   => 'required|integer',
            'locale'    => 'nullable|in:'.implode(',', array_keys(config('app.available_locales'))),
            'status'    => 'nullable|boolean',
        ]);

        // Super admins carry no tenant; everyone else must belong to one.
        $tenantId = $user->is_super_admin ? null : ($validated['tenant_id'] ?? null);

        if (! $user->is_super_admin && ! $tenantId) {
            return back()->withInput()->withErrors([
                'tenant_id' => trans('azimio::app.users.tenant-required'),
            ]);
        }

        if ($tenantId) {
            $this->assertRoleBelongsToTenant($validated['role_id'], $tenantId);
        }

        // tenant_id is not mass-assignable, so forceFill it alongside the rest.
        $user->forceFill([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'tenant_id' => $tenantId,
            'role_id'   => $validated['role_id'],
            'locale'    => $validated['locale'] ?: null,
            'status'    => request()->boolean('status'),
        ])->save();

        session()->flash('success', trans('azimio::app.users.updated'));

        return redirect()->route('super.users.index');
    }

    /**
     * Set a new password for a user.
     */
    public function updatePassword(int $id)
    {
        $user = User::withoutTenancy()->findOrFail($id);

        $validated = request()->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        session()->flash('success', trans('azimio::app.users.password-updated'));

        return redirect()->route('super.users.edit', $user->id);
    }

    /**
     * Delete a user. A super admin may not delete their own account, and the
     * last remaining super admin is protected so the platform is never locked out.
     */
    public function destroy(int $id)
    {
        $user = User::withoutTenancy()->findOrFail($id);

        if ($user->id === auth()->guard('user')->id()) {
            session()->flash('error', trans('azimio::app.users.cannot-delete-self'));

            return redirect()->route('super.users.index');
        }

        if ($user->is_super_admin && User::withoutTenancy()->where('is_super_admin', 1)->count() <= 1) {
            session()->flash('error', trans('azimio::app.users.last-super-admin'));

            return redirect()->route('super.users.index');
        }

        $user->delete();

        session()->flash('success', trans('azimio::app.users.deleted'));

        return redirect()->route('super.users.index');
    }

    /**
     * All roles, unscoped, each tagged with the tenant it belongs to (NULL =
     * shared platform default). The view filters these by the selected tenant.
     */
    protected function rolesForSelect()
    {
        return Role::withoutTenancy()->orderBy('name')->get(['id', 'name', 'tenant_id']);
    }

    /**
     * Guard against assigning a role that belongs to a different tenant. A role
     * is valid for a tenant when it is that tenant's own role or a shared
     * platform default (NULL tenant_id).
     */
    protected function assertRoleBelongsToTenant(int $roleId, int $tenantId): void
    {
        $role = Role::withoutTenancy()->findOrFail($roleId);

        abort_unless(
            is_null($role->tenant_id) || $role->tenant_id === $tenantId,
            422,
            trans('azimio::app.users.role-mismatch')
        );
    }
}
