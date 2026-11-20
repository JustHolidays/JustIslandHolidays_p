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

        // Trusted proxies are set in AppServiceProvider from config('site.trusted_proxies'),
        // so they still work when the config is cached on the server.

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAccountIsActive::class,
            // Admins: emailed code or authenticator app before any signed-in page.
            \App\Http\Middleware\RequireSecondFactor::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sign out always works (20 Nov 2026): a page left open past the session lifetime has an
        // out-of-date form token, which would otherwise show "Page Expired" instead of signing out.
        // Laravel turns the token mismatch into a 419 before these callbacks run.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException || ! $request->routeIs('logout')) {
                return null;
            }
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('status', 'You are signed out.');
        });
    })->create();
