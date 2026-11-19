<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Admin pages that only a super admin can open. Island admins get a 403 and the links are hidden.
     */
    public const SUPER_ADMIN_ABILITIES = [
        'manage-staff',          // /admin/staff
        'manage-affiliates',     // /admin/affiliates
        'manage-appearance',     // /admin/appearance
        'manage-plans',          // /admin/plans
        'view-audit-log',        // /admin/audit
        'manage-islands',        // create, rename, reorder, activate islands and resorts
        'manage-amenities',      // /admin/amenities
        'handle-data-requests',  // /admin/privacy
        'import-commissions',    // /admin/commissions import and edit
        'decide-recommendations',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (filled($proxies = config('site.trusted_proxies'))) {
            \Illuminate\Http\Middleware\TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // An admin who passed the authenticator app challenge at sign-in has done the
        // second step for this session (App\Auth\SecondFactor). Fires before the login.
        Event::listen(\Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided::class, function ($event) {
            if ($event->user instanceof User) {
                \App\Auth\SecondFactor::markPassed($event->user);
            }
        });

        // Last sign-in for the Staff and Owners lists. Fires after the two-factor challenge.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        foreach (self::SUPER_ADMIN_ABILITIES as $ability) {
            Gate::define($ability, fn (User $user) => $user->isSuperAdmin());
        }

        Gate::define('access-admin', fn (User $user) => $user->isAdmin());

        // Island admins see their islands; super admins all. AdminScope limits the rows.
        Gate::define('view-click-reports', fn (User $user) => $user->isAdmin());

        // Checkout, customer portal and billing endpoints refuse affiliates and admins.
        Gate::define('use-billing', fn (User $user) => $user->role?->canSubscribe() ?? false);

        // Owners, agents and affiliates hold listings and use the account listing pages.
        Gate::define('hold-listings', fn (User $user) => $user->role?->isLister() ?? false);

        // Owners, agents and affiliates see clicks for their own listings in /account/clicks.
        Gate::define('view-own-clicks', fn (User $user) => $user->role?->isLister() ?? false);
    }
}
