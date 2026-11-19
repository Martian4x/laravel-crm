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

            /**
             * Registered but not verified (7.2). Deliberately a different
             * outcome from suspension: they have not been refused, they
             * simply have not finished signing up, so send them to the
             * verification notice rather than logging them out with a
             * "suspended" message they could not act on.
             *
             * The verification routes themselves are outside this
             * middleware, so this cannot loop.
             */
            if ($tenant->isPending()) {
                session()->put('azimio_pending_email', $user->email);

                return redirect()->route('azimio.verify.notice');
            }

            app(TenantManager::class)->set($tenant);
        }

        return $next($request);
    }
}
