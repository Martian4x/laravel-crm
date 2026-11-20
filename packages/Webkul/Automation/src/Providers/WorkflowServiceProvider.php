<?php

namespace Webkul\Automation\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Automation\Listeners\Entity;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen('*', function ($eventName, array $data) {
            // AZIMIO FIX: data_get() returns null whenever the workflows
            // config is not loaded, and in_array() with a null haystack is a
            // TypeError on PHP 8. This listener is bound to '*', so it runs
            // for EVERY event -- including the console events `config:cache`
            // dispatches while a fresh application instance is the facade
            // root and the package configs have not been merged into it.
            //
            // The result was that `artisan config:cache` exited 255 on every
            // run, and Webkul's own exception handler calls trans() in its
            // constructor, so the TypeError surfaced as the useless "Class
            // translator does not exist" with the real cause nowhere in
            // sight. Production therefore ran with NO cached config at all.
            //
            // Guarding the haystack is enough; a null config simply means
            // there are no workflow triggers to match yet.
            $triggers = data_get(config('workflows.trigger_entities'), '*.events.*.event');

            if (! is_array($triggers) || ! in_array($eventName, $triggers)) {
                return;
            }

            app(Entity::class)->process($eventName, current($data));
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/workflows.php', 'workflows');
    }
}
