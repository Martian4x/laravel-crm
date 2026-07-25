<?php

namespace Azimio\Tenancy\Providers;

use Azimio\Tenancy\Http\Controllers\TenantController;
use Azimio\Tenancy\Http\Controllers\UserController;
use Azimio\Tenancy\Http\Middleware\EnsureSuperAdmin;
use Azimio\Tenancy\Http\Middleware\IdentifyTenant;
use Azimio\Tenancy\Listeners\ScopeDataGridToTenant;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Mail\Events\MessageSending;
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

        /**
         * Outgoing mail sends from the platform address (SPF-safe) but with
         * the tenant's name as the display name, so recipients see the
         * company they deal with, not the platform.
         */
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $tenant = tenant();

            if (! $tenant) {
                return;
            }

            $from = $event->message->getFrom();

            if (! empty($from)) {
                $event->message->from(
                    new \Symfony\Component\Mime\Address($from[0]->getAddress(), $tenant->name)
                );
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Azimio\Tenancy\Console\Commands\CreateTenant::class,
                \Azimio\Tenancy\Console\Commands\AdoptOrphanData::class,
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

                Route::get('tenants/{id}/edit', [TenantController::class, 'edit'])->name('super.tenants.edit');

                Route::put('tenants/{id}', [TenantController::class, 'update'])->name('super.tenants.update');

                Route::delete('tenants/{id}', [TenantController::class, 'destroy'])->name('super.tenants.destroy');

                Route::post('tenants/{id}/impersonate', [TenantController::class, 'impersonate'])->name('super.tenants.impersonate');

                // Cross-tenant user management.
                Route::get('users', [UserController::class, 'index'])->name('super.users.index');

                Route::get('users/create', [UserController::class, 'create'])->name('super.users.create');

                Route::post('users', [UserController::class, 'store'])->name('super.users.store');

                Route::get('users/{id}/edit', [UserController::class, 'edit'])->name('super.users.edit');

                Route::put('users/{id}', [UserController::class, 'update'])->name('super.users.update');

                Route::put('users/{id}/password', [UserController::class, 'updatePassword'])->name('super.users.password');

                Route::delete('users/{id}', [UserController::class, 'destroy'])->name('super.users.destroy');
            });

        // Reachable while impersonating a tenant user, hence no super_admin middleware.
        Route::middleware(['web', 'user'])
            ->prefix(config('app.admin_path').'/super')
            ->post('revert', [TenantController::class, 'revert'])->name('super.tenants.revert');

        // Public: tenant self-registration + legal pages.
        Route::middleware(['web'])->group(function () {
            Route::get('register', [\Azimio\Tenancy\Http\Controllers\RegisterController::class, 'create'])
                ->name('azimio.register.create');

            Route::post('register', [\Azimio\Tenancy\Http\Controllers\RegisterController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('azimio.register.store');

            Route::view('terms', 'azimio::legal.terms')->name('azimio.terms');

            Route::view('privacy', 'azimio::legal.privacy')->name('azimio.privacy');
        });
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
