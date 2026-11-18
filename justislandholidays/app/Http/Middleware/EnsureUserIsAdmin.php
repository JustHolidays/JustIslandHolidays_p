<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin area gate: island admin or super admin, with two-factor set up.
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

        if (! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('account.security')
                ->with('status', 'Set up two-factor sign-in before you use the admin area.');
        }

        if ($user->isIslandAdmin() && $user->managedIslandIds() === []) {
            abort(403, 'No islands are assigned to your account yet. Ask a super admin.');
        }

        return $next($request);
    }
}
