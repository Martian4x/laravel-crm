<?php

namespace Azimio\Tenancy\Providers;

use Azimio\Tenancy\Console\Commands\AdoptOrphanData;
use Azimio\Tenancy\Console\Commands\CreateTenant;
use Azimio\Tenancy\Console\Commands\ExpireTrials;
use Azimio\Tenancy\Database\Seeders\AzimioAttributeSeeder;
use Azimio\Tenancy\Http\Controllers\OnboardingController;
use Azimio\Tenancy\Http\Controllers\RegisterController;
use Azimio\Tenancy\Http\Controllers\TenantController;
use Azimio\Tenancy\Http\Controllers\UserController;
use Azimio\Tenancy\Http\Controllers\VerifyEmailController;
use Azimio\Tenancy\Http\Middleware\EnsureSuperAdmin;
use Azimio\Tenancy\Http\Middleware\IdentifyTenant;
use Azimio\Tenancy\Listeners\ScopeDataGridToTenant;
use Azimio\Tenancy\Models\Tenant;
use Azimio\Tenancy\TenantManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;

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

        /**
         * 9.1: re-apply our attributes after a fresh install.
         *
         * `krayin-crm:install` runs `migrate:fresh` and THEN the seeders,
         * and AttributeSeeder's first act is to delete every row in
         * `attributes`. Anything a migration put there is therefore gone by
         * the time the install finishes, with the migration still recorded
         * as run and nothing saying otherwise. That is how the TIN/VRN and
         * mobile-money fields stayed ticked off for months while never
         * existing on an installed system.
         *
         * `krayin.installed` fires after the seeders (Installer.php:315),
         * which is the first moment these rows can survive.
         */
        Event::listen('krayin.installed', function () {
            app(AzimioAttributeSeeder::class)->run();
        });

        /**
         * 7.6: the first-login checklist, injected through Krayin's own
         * view_render_event hook rather than by editing the dashboard
         * view — so an upstream merge cannot drop it or conflict with it.
         */
        Event::listen('admin.dashboard.index.header.after', function ($manager) {
            $manager->addTemplate('azimio::partials.onboarding');
        });

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
                    new Address($from[0]->getAddress(), $tenant->name)
                );
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateTenant::class,
                AdoptOrphanData::class,
                ExpireTrials::class,
            ]);

            /**
             * 7.5: suspend lapsed trials daily. Scheduled from the package
             * so an upstream merge cannot drop it.
             */
            $this->app->booted(function () {
                $this->app->make(Schedule::class)
                    ->command('azimio:tenant:expire-trials')
                    ->dailyAt('02:00')
                    ->withoutOverlapping();
            });
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

                // 7.12: offboarding export.
                Route::get('tenants/{id}/export', [TenantController::class, 'export'])->name('super.tenants.export');

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

        // 7.6: dismissing the onboarding checklist. Needs tenant context,
        // since the flag is stored in tenant-scoped core_config.
        Route::middleware(['web', 'user', 'tenant', 'admin_locale'])
            ->prefix(config('app.admin_path'))
            ->post('onboarding/dismiss', [OnboardingController::class, 'dismiss'])
            ->name('azimio.onboarding.dismiss');

        // Reachable while impersonating a tenant user, hence no super_admin middleware.
        Route::middleware(['web', 'user'])
            ->prefix(config('app.admin_path').'/super')
            ->post('revert', [TenantController::class, 'revert'])->name('super.tenants.revert');

        // Public: tenant self-registration + legal pages.
        Route::middleware(['web'])->group(function () {
            Route::get('register', [RegisterController::class, 'create'])
                ->name('azimio.register.create');

            Route::post('register', [RegisterController::class, 'store'])
                ->middleware('throttle:5,1')
                ->name('azimio.register.store');

            /**
             * Email verification (7.2). Outside the 'tenant' middleware on
             * purpose: a pending tenant is redirected here BY that
             * middleware, so these routes must not be subject to it.
             */
            Route::get('verify', [VerifyEmailController::class, 'notice'])
                ->name('azimio.verify.notice');

            Route::get('verify/{id}', [VerifyEmailController::class, 'verify'])
                ->name('azimio.verify.verify');

            Route::post('verify/resend', [VerifyEmailController::class, 'resend'])
                ->middleware('throttle:5,1')
                ->name('azimio.verify.resend');

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
