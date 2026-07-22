<?php

namespace Azimio\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local development seeder: 2 fake tenants with one admin user each,
 * plus the primary account promoted to super admin. Idempotent.
 */
class DevTenantSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $tenants = [
            [
                'name'   => 'Twiga Traders Ltd',
                'slug'   => 'twiga-traders',
                'locale' => 'sw',
            ],
            [
                'name'   => 'Kilimanjaro Suppliers Co',
                'slug'   => 'kilimanjaro-suppliers',
                'locale' => 'en',
            ],
        ];

        foreach ($tenants as $tenant) {
            DB::table('tenants')->updateOrInsert(
                ['slug' => $tenant['slug']],
                array_merge($tenant, [
                    'status'     => 1,
                    'currency'   => 'TZS',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

            $tenantId = DB::table('tenants')->where('slug', $tenant['slug'])->value('id');

            DB::table('users')->updateOrInsert(
                ['email' => 'admin@'.$tenant['slug'].'.test'],
                [
                    'name'            => $tenant['name'].' Admin',
                    'password'        => bcrypt('password'),
                    'status'          => 1,
                    'is_super_admin'  => 0,
                    'tenant_id'       => $tenantId,
                    'role_id'         => 1,
                    'view_permission' => 'global',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]
            );
        }

        DB::table('users')
            ->where('email', 'admin@azimio.co.tz')
            ->update([
                'is_super_admin' => 1,
                'tenant_id'      => null,
            ]);
    }
}
