<?php

namespace Azimio\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    /**
     * Only platform super admins (tenant_id NULL + is_super_admin) may pass.
     * 404 (not 403) so the panel's existence is not revealed to tenants.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->guard('user')->user();

        if (! $user || ! $user->is_super_admin || $user->tenant_id) {
            abort(404);
        }

        return $next($request);
    }
}
