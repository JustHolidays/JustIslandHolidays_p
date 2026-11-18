<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // /go/{ref}: no web middleware group, so no session and no cookies.
            \Illuminate\Support\Facades\Route::middleware([])->group(base_path('routes/tracking.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Every request, including /go and webhooks.
        $middleware->prepend(\App\Http\Middleware\CanonicalHost::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Behind the host's CDN or load balancer, set TRUSTED_PROXIES to its addresses (comma-separated)
        // so https and the visitor's address are read correctly. Off by default: trusting every proxy
        // would let anyone fake their address and slip past the enquiry rate limit.
        if (filled(env('TRUSTED_PROXIES'))) {
            $middleware->trustProxies(at: env('TRUSTED_PROXIES') === '*' ? '*' : explode(',', env('TRUSTED_PROXIES')));
        }

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
