<?php

namespace Azimio\Tenancy\Http\Middleware;

use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenant
{
    /**
     * Resolve the active tenant from the authenticated user and set it for
     * the request. Super admins (tenant_id NULL) run unscoped. Users of a
     * suspended or deleted tenant are logged out.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->guard('user')->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if (! $tenant || ! $tenant->isActive()) {
                auth()->guard('user')->logout();

                session()->flash('error', trans('admin::app.users.suspended-tenant'));

                return redirect()->route('admin.session.create');
            }

            app(TenantManager::class)->set($tenant);
        }

        return $next($request);
    }
}
