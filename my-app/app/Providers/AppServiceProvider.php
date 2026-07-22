<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Codespaces' port-forwarding proxy terminates HTTPS at the edge and
        // doesn't preserve the original public Host when it connects to this
        // container, so every request Laravel sees looks like it came in on
        // http://localhost:8000. Left alone, that makes url()/route() (and
        // anything built on them, including the framework's own "redirect
        // guest to login" behavior) generate wrong, unreachable absolute
        // URLs. The forwarded domain is deterministic from CODESPACE_NAME,
        // so we tell Laravel its real public root explicitly — same fix
        // shape as the Vite dev server config, and inert everywhere else.
        if ($codespaceName = env('CODESPACE_NAME')) {
            $forwardedRoot = "https://{$codespaceName}-8000.".env('GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN');

            URL::forceRootUrl($forwardedRoot);
            URL::forceScheme('https');

            // Pagination links are a special case: Illuminate\Pagination's
            // own service provider resolves the current path straight from
            // the raw request (request()->url()) rather than through the
            // URL generator, so they inherit the same unreachable
            // localhost:8000 that forceRootUrl above exists to paper over.
            // Route the paginator's path through the forced root too.
            Paginator::currentPathResolver(
                fn () => $forwardedRoot.request()->getPathInfo()
            );
        }
    }
}
