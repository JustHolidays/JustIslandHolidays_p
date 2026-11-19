<?php

namespace App\Http\Middleware;

use App\Auth\SecondFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In the web group, so it covers every signed-in page however the admin signed in
 * (password, remember-me cookie or an invitation link). An admin whose session has
 * not passed the second step can only reach the verify page and sign out.
 */
class RequireSecondFactor
{
    private const ALLOWED = ['second-factor.show', 'second-factor.verify', 'second-factor.resend', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (SecondFactor::required($user) && ! SecondFactor::passed($user) && ! $request->routeIs(...self::ALLOWED)) {
            return redirect()->guest(route('second-factor.show'));
        }

        return $next($request);
    }
}
