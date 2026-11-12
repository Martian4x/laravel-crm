<?php

namespace Azimio\Messaging\Providers;

use Azimio\Messaging\DriverManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * AzimioCustomers — Text Messaging (SMS) module.
 *
 * Mirrors the structure of Azimio\Tenancy. The one deliberate difference is
 * routing: this module has three distinct route surfaces (tenant admin,
 * super admin, public webhooks) with different middleware stacks, so they
 * live in separate files under src/Routes rather than inline in this class.
 *
 * Specification: AZIMIO_TODO.md Phase 8.
 */
class MessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/messaging.php',
            'messaging'
        );

        /**
         * The manager is a singleton; the drivers it builds are not. Each
         * driver instance carries one sender profile's credentials, so
         * sharing one across tenants would send through the wrong account.
         */
        $this->app->singleton(DriverManager::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'messaging');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'messaging');

        $this->registerRoutes();

        $this->registerListeners();

        $this->publishes([
            __DIR__.'/../Config/messaging.php' => config_path('messaging.php'),
        ], 'messaging-config');

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }
    }

    /**
     * Three route surfaces, three middleware stacks.
     *
     * The tenant stack matches the admin group established in Phase 3.4
     * ('tenant' before 'admin_locale', so tenant context exists before any
     * config read). Webhooks are public and therefore carry no session: they
     * are signature-verified in the controller and resolve tenant context
     * from the sender profile, never from the request body.
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $adminPath = config('app.admin_path');

        // Tenant-facing SMS module.
        Route::middleware(['web', 'user', 'tenant', 'admin_locale'])
            ->prefix($adminPath)
            ->group(__DIR__.'/../Routes/admin.php');

        // Platform-admin SMS control.
        Route::middleware(['web', 'user', 'super_admin', 'admin_locale'])
            ->prefix($adminPath.'/super')
            ->group(__DIR__.'/../Routes/super.php');

        // Public provider callbacks.
        if (config('messaging.webhooks.enabled')) {
            Route::middleware(['throttle:'.config('messaging.webhooks.rate_limit')])
                ->prefix('messaging/webhook')
                ->group(__DIR__.'/../Routes/webhook.php');
        }
    }

    /**
     * Event listeners (delivery reports, workflow hooks, notification
     * channel registration). Populated from 8.19 and 8.20 onward.
     *
     * @return void
     */
    protected function registerListeners(): void
    {
        //
    }

    /**
     * Console commands (batch dispatch, delivery-report polling, credit
     * reconciliation). Populated from 8.5, 8.15 and 8.19 onward.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        $this->commands([
            //
        ]);
    }
}
