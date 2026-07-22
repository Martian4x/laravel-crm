<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class Locale
{
    /**
     * The middleware instance.
     *
     * @return void
     */
    public function __construct(
        Application $app,
        Request $request
    ) {
        $this->app = $app;

        $this->request = $request;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $userLocale = auth()->guard('user')->user()?->locale;

        if ($userLocale && array_key_exists($userLocale, config('app.available_locales'))) {
            app()->setLocale($userLocale);

            return $next($request);
        }

        app()->setLocale(
            core()->getConfigData('general.general.locale_settings.locale')
                ?: app()->getLocale()
        );

        return $next($request);
    }
}
