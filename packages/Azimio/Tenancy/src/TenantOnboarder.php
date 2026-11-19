<?php

namespace Azimio\Tenancy;

use Azimio\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantOnboarder
{
    /**
     * Create a tenant with its first admin user.
     *
     * Shared platform defaults (pipelines, sources, types, roles, attributes,
     * email templates — rows with NULL tenant_id) are visible to every tenant,
     * so a fresh tenant is fully usable right after this runs.
     *
     * $tenant: name (required), slug?, locale?, currency?, plan?, max_users?
     * $admin:  name (required), email (required), password (required)
     */
    public function onboard(array $tenant, array $admin): Tenant
    {
        return DB::transaction(function () use ($tenant, $admin) {
            $model = Tenant::create([
                'name'      => $tenant['name'],
                'slug'      => $tenant['slug'] ?? Str::slug($tenant['name']),
                'locale'    => $tenant['locale'] ?? config('app.locale'),
                'currency'  => $tenant['currency'] ?? config('app.currency'),
                'plan'      => $tenant['plan'] ?? null,
                'max_users' => $tenant['max_users'] ?? null,
                'status'    => Tenant::STATUS_ACTIVE,
            ]);

            /**
             * activated_at is deliberately NOT mass-assigned: a tenant
             * created by a super admin or the console is vouched for and
             * starts activated, while a public self-registration starts
             * pending until its admin verifies their email (7.2). The
             * caller says which by passing 'pending' => true.
             */
            $model->forceFill([
                'activated_at'  => ($tenant['pending'] ?? false) ? null : now(),
                'trial_ends_at' => $tenant['trial_ends_at'] ?? null,
            ])->save();

            DB::table('users')->insert([
                'name'            => $admin['name'],
                'email'           => $admin['email'],
                'password'        => bcrypt($admin['password']),
                'tenant_id'       => $model->id,
                'locale'          => $model->locale,
                'status'          => 1,
                // Verified unless this is a pending self-registration (7.2).
                'email_verified_at' => ($tenant['pending'] ?? false) ? null : now(),
                'is_super_admin'  => 0,
                'role_id'         => $this->defaultAdminRoleId(),
                'view_permission' => 'global',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return $model;
        });
    }

    /**
     * The shared platform "Administrator" role (NULL tenant_id).
     */
    protected function defaultAdminRoleId(): int
    {
        $roleId = DB::table('roles')
            ->whereNull('tenant_id')
            ->where('permission_type', 'all')
            ->value('id');

        if (! $roleId) {
            throw new \RuntimeException('No platform admin role found. Seed the roles table first.');
        }

        return $roleId;
    }
}
