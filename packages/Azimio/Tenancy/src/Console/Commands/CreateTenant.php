<?php

namespace Azimio\Tenancy\Console\Commands;

use Azimio\Tenancy\TenantOnboarder;
use Illuminate\Console\Command;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'azimio:tenant:create
        {name : Company name}
        {admin-email : Email of the tenant admin}
        {--admin-name= : Name of the tenant admin (defaults to company name + " Admin")}
        {--password= : Admin password (generated if omitted)}
        {--locale=sw : Default language (en|sw)}
        {--slug= : URL-safe identifier (generated from name if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant (company) with its first admin user';

    public function handle(TenantOnboarder $onboarder): int
    {
        $password = $this->option('password') ?: str()->password(12);

        if (! array_key_exists($this->option('locale'), config('app.available_locales'))) {
            $this->error('Locale must be one of: '.implode(', ', array_keys(config('app.available_locales'))));

            return self::FAILURE;
        }

        $tenant = $onboarder->onboard(
            [
                'name'   => $this->argument('name'),
                'slug'   => $this->option('slug'),
                'locale' => $this->option('locale'),
            ],
            [
                'name'     => $this->option('admin-name') ?: $this->argument('name').' Admin',
                'email'    => $this->argument('admin-email'),
                'password' => $password,
            ]
        );

        $this->info("Tenant #{$tenant->id} '{$tenant->name}' created (slug: {$tenant->slug}).");
        $this->info('Admin login: '.$this->argument('admin-email'));

        if (! $this->option('password')) {
            $this->warn('Generated password: '.$password);
        }

        return self::SUCCESS;
    }
}
