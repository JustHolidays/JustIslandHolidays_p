<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One main address. When SITE_CANONICAL_HOST is set (for example justislandholidays.co.uk),
 * any request to another of the site's hosts (the other domain, www.) gets a permanent
 * redirect to the same path on the main host over https.
 * Switching the main domain is a one-line change in .env.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = strtolower((string) config('site.canonical_host'));
        $aliases = array_map('strtolower', config('site.redirect_hosts'));
        $host = strtolower($request->getHost());

        if ($canonical !== '' && $host !== $canonical && in_array($host, $aliases, true)) {
            return redirect()->away('https://'.$canonical.$request->getRequestUri(), 301);
        }

        // Force https on the main host when it is configured.
        if ($canonical !== '' && $host === $canonical && ! $request->isSecure() && app()->isProduction()) {
            return redirect()->away('https://'.$canonical.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
