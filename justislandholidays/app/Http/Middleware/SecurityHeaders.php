<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response. HSTS only over https, so local http development
 * still works. The admin and account areas cannot be framed by other sites.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        if ($request->routeIs('calendar.embed')) {
            // The booking calendar embed is made to sit in an iframe on owners' own websites.
            $headers->remove('X-Frame-Options');
            $headers->set('Content-Security-Policy', 'frame-ancestors *');
        } else {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $headers->set('Referrer-Policy', $headers->get('Referrer-Policy') ?? 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Before launch: nothing on the site may be indexed.
        if (config('site.block_indexing')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
