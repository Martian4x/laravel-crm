<?php

namespace Azimio\Tenancy\Providers;

use Azimio\Tenancy\Http\Middleware\IdentifyTenant;
use Azimio\Tenancy\Listeners\ScopeDataGridToTenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TenantManager::class);

        require_once __DIR__.'/../helpers.php';
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $router->aliasMiddleware('tenant', IdentifyTenant::class);

        Event::listen('datagrid.*', ScopeDataGridToTenant::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Azimio\Tenancy\Console\Commands\CreateTenant::class,
            ]);
        }
    }
}
