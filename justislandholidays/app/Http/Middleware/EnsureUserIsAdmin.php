<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin area gate: island admin or super admin who has done the second sign-in step
 * (emailed code or authenticator app) in this session.
 * An island admin with no islands assigned is refused.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        // Belt and braces: RequireSecondFactor (web group) already keeps an admin who has
        // not done the second sign-in step on the verify page.
        if (! \App\Auth\SecondFactor::passed($user)) {
            return redirect()->guest(route('second-factor.show'));
        }

        if ($user->isIslandAdmin() && $user->managedIslandIds() === []) {
            abort(403, 'No islands are assigned to your account yet. Ask a super admin.');
        }

        return $next($request);
    }
}
