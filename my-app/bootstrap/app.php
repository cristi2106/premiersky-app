<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Codespaces (and any HTTPS-terminating load balancer) reaches this
        // container over plain HTTP with Host: localhost:8000, but sends the
        // real public origin in X-Forwarded-Host/-Proto/-Port. Trusting those
        // is what lets url()/route()/redirect() build absolute URLs that point
        // back at the origin the browser actually used. Without it Laravel
        // builds every redirect from the internal host, which sends the
        // browser to a *different* origin than the one holding its session
        // cookie. The proxy is the only route into this container, so there is
        // no untrusted path that could spoof these headers.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
