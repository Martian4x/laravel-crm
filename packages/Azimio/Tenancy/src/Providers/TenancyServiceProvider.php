<?php

namespace Azimio\Tenancy\Providers;

use Azimio\Tenancy\Http\Controllers\TenantController;
use Azimio\Tenancy\Http\Middleware\EnsureSuperAdmin;
use Azimio\Tenancy\Http\Middleware\IdentifyTenant;
use Azimio\Tenancy\Listeners\ScopeDataGridToTenant;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
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

        $router->aliasMiddleware('super_admin', EnsureSuperAdmin::class);

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'azimio');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'azimio');

        $this->registerRoutes();

        Event::listen('datagrid.*', ScopeDataGridToTenant::class);

        $this->propagateTenantToQueue();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Azimio\Tenancy\Console\Commands\CreateTenant::class,
            ]);
        }
    }

    /**
     * Super-admin tenant panel + impersonation revert.
     */
    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'user', 'super_admin', 'admin_locale'])
            ->prefix(config('app.admin_path').'/super')
            ->group(function () {
                Route::get('tenants', [TenantController::class, 'index'])->name('super.tenants.index');

                Route::post('tenants', [TenantController::class, 'store'])->name('super.tenants.store');

                Route::post('tenants/{id}/toggle', [TenantController::class, 'toggleStatus'])->name('super.tenants.toggle');

                Route::post('tenants/{id}/impersonate', [TenantController::class, 'impersonate'])->name('super.tenants.impersonate');
            });

        // Reachable while impersonating a tenant user, hence no super_admin middleware.
        Route::middleware(['web', 'user'])
            ->prefix(config('app.admin_path').'/super')
            ->post('revert', [TenantController::class, 'revert'])->name('super.tenants.revert');
    }

    /**
     * Every queued job (mail, imports, notifications) is stamped with the
     * tenant active at dispatch time, and the worker restores that context
     * before processing — so tenant scoping holds inside jobs too.
     */
    protected function propagateTenantToQueue(): void
    {
        Queue::createPayloadUsing(function () {
            return ['azimio_tenant_id' => tenant_id()];
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            $tenantId = $event->job->payload()['azimio_tenant_id'] ?? null;

            $manager = app(TenantManager::class);

            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            $tenant ? $manager->set($tenant) : $manager->forget();
        });

        Event::listen(JobProcessed::class, fn () => app(TenantManager::class)->forget());

        Event::listen(JobFailed::class, fn () => app(TenantManager::class)->forget());
    }
}
