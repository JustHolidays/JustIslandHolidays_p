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
        'decide-recommendations',
        'manage-ads',            // /admin/ads: island page adverts (super admins only, decided 19 Nov 2026)
        'manage-property-types', // /admin/property-types (super admins only, decided 19 Nov 2026)
        'manage-paypal-settings', // /admin/paypal-settings: PayPal details (super admins only, 20 Nov 2026)
        'manage-account-closures', // /admin/account-closures: "Close my account" requests (super admins only, 20 Nov 2026)
        'manage-listing-csv',    // /admin/listings/csv: listing CSV import and export (super admins only, 20 Nov 2026)
        'manage-seo',            // /admin/seo: sitemap, titles and descriptions, sharing (super admins only, 20 Nov 2026)
        'manage-maintenance',    // /admin/maintenance: maintenance mode (super admins only, 20 Nov 2026)
        'manage-owners',         // /admin/users/add: add owner and agent accounts (super admins only, 20 Nov 2026)
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // PayPal details saved on PayPal Settings take the place of the .env ones.
        \App\Support\PayPalSettings::apply();

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
                // A heart pressed before signing in (or creating an account) is saved now.
                if (app()->bound('session') && request()->hasSession()) {
                    \App\Listings\Favourites::savePending($event->user);
                }
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
