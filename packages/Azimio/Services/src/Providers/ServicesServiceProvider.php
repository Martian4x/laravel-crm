<?php

namespace Azimio\Services\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * The Services module (9.4) — what a firm sells that is not stock.
 *
 * Deliberately thin: services are ordinary tenant-scoped rows, so there is
 * no manager, no driver and nothing to bind. Everything the module needs is
 * the model (which carries BelongsToTenant) and the routes below.
 */
class ServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'services');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'services');

        $this->registerRoutes();
    }

    /**
     * The tenant stack matches the admin group from Phase 3.4: 'tenant'
     * before 'admin_locale', so tenant context exists before any config
     * read, and 'user' applies the `services*` ACL keys.
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'user', 'tenant', 'admin_locale'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin.php');
    }
}
