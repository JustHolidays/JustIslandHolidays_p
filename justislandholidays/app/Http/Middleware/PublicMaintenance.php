<?php

namespace App\Http\Middleware;

use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the maintenance page on public pages while maintenance mode is on (20 Nov 2026).
 * Signed-in admins pass through and see the site as normal; they can view the maintenance
 * page itself with ?maintenance=preview.
 */
class PublicMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...Maintenance::PUBLIC_ROUTES)) {
            return $next($request);
        }

        $isAdmin = $request->user()?->isAdmin() ?? false;
        $preview = $isAdmin && $request->query('maintenance') === 'preview';
        $settings = Maintenance::settings();

        if ((! $settings['on'] || $isAdmin) && ! $preview) {
            return $next($request);
        }

        $headers = ['Cache-Control' => 'no-store'];
        if ($settings['until'] && $settings['until']->isFuture()) {
            $headers['Retry-After'] = (string) max(60, (int) now()->diffInSeconds($settings['until']));
        }

        return response()->view('maintenance', ['settings' => $settings, 'preview' => $preview], $preview ? 200 : 503, $headers);
    }
}
