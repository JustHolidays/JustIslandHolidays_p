<?php

use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\PropertyTypeController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ClickController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeeplinkController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IslandController;
use App\Http\Controllers\Admin\IslandSelectorController;
use App\Http\Controllers\Admin\LicenceController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RecommendationController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin area at /admin. Every route: signed in, email verified, island admin or super admin,
| two-factor set up. Every list goes through AdminScope (App\Admin\AdminContext); a record on
| another island opened by URL is refused with 403. Super admin pages are behind gates.
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/island', IslandSelectorController::class)->name('island');

    // Listings and review
    Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
    Route::post('/listings/bulk', [ListingController::class, 'bulk'])->name('listings.bulk');
    Route::get('/listings/import', [ImportController::class, 'create'])->name('import.create');
    Route::post('/listings/import/fetch', [ImportController::class, 'fetch'])->middleware('throttle:20,1')->name('import.fetch');
    Route::post('/listings/import', [ImportController::class, 'store'])->name('import.store');
    // Add a property for an owner or agent (island admins for their own islands).
    // Listing CSV import and export (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-listing-csv')->prefix('/listings/csv')->name('listings.csv')->group(function () {
        $csv = \App\Http\Controllers\Admin\ListingCsvController::class;
        Route::get('/', [$csv, 'index']);
        Route::get('/export', [$csv, 'export'])->middleware('throttle:10,1')->name('.export');
        Route::get('/template', [$csv, 'template'])->name('.template');
        Route::post('/', [$csv, 'upload'])->middleware('throttle:10,1')->name('.upload');
        Route::get('/preview/{token}', [$csv, 'preview'])->where('token', '[A-Za-z0-9]{32}')->name('.preview');
        Route::post('/preview/{token}', [$csv, 'apply'])->where('token', '[A-Za-z0-9]{32}')->name('.apply');
    });
    Route::get('/listings/add', [\App\Http\Controllers\Admin\PropertyCreateController::class, 'create'])->name('listings.create');
    Route::post('/listings/add', [\App\Http\Controllers\Admin\PropertyCreateController::class, 'store'])->name('listings.store');
    // Full property editor for owner and agent properties (20 Nov 2026): the owner's own editor
    // steps, run in the admin area. Super admins, and island admins on their own islands.
    Route::middleware('can:editAsAdmin,listing')->prefix('/listings/{listing}/editor')->name('listings.editor.')->group(function () {
        $editor = \App\Http\Controllers\Account\ListingEditorController::class;
        $calendar = \App\Http\Controllers\Account\CalendarController::class;
        $rates = \App\Http\Controllers\Account\RatesController::class;

        Route::get('/{step?}', [$editor, 'edit'])->where('step', 'basics|location|photo|link|calendar|bookings|rates')->name('edit');
        Route::put('/basics', [$editor, 'updateBasics'])->name('basics');
        Route::put('/location', [$editor, 'updateLocation'])->name('location');
        Route::put('/photo', [$editor, 'updatePhoto'])->name('photo');
        Route::post('/photos', [$editor, 'addPhoto'])->name('photos.store');
        Route::delete('/photos/{photo}', [$editor, 'removePhoto'])->whereNumber('photo')->name('photos.destroy');
        Route::put('/link', [$editor, 'updateLink'])->name('link');
        Route::post('/calendar/feeds', [$calendar, 'addFeed'])->middleware('throttle:10,1')->name('calendar.feeds.store');
        Route::post('/calendar/feeds/{feed}/sync', [$calendar, 'syncFeed'])->whereNumber('feed')->middleware('throttle:10,1')->name('calendar.feeds.sync');
        Route::delete('/calendar/feeds/{feed}', [$calendar, 'removeFeed'])->whereNumber('feed')->name('calendar.feeds.destroy');
        Route::post('/calendar/bookings', [$calendar, 'addBooking'])->name('calendar.bookings.store');
        Route::put('/calendar/changeover', [$calendar, 'saveChangeover'])->name('calendar.changeover');
        Route::post('/calendar/toggle', [$calendar, 'toggleNight'])->name('calendar.toggle');
        Route::put('/calendar/bookings/{booking}', [$calendar, 'updateBooking'])->whereNumber('booking')->name('calendar.bookings.update');
        Route::delete('/calendar/bookings/{booking}', [$calendar, 'removeBooking'])->whereNumber('booking')->name('calendar.bookings.destroy');
        Route::post('/rates', [$rates, 'store'])->middleware('throttle:30,1')->name('rates.store');
        Route::delete('/rates/{rate}', [$rates, 'destroy'])->whereNumber('rate')->name('rates.destroy');
    });

    Route::get('/listings/{listing}', [ListingController::class, 'show'])->middleware('can:view,listing')->name('listings.show');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::post('/listings/{listing}/approve', [ListingController::class, 'approve'])->name('listings.approve');
    Route::post('/listings/{listing}/publish-draft', [ListingController::class, 'publishDraft'])->name('listings.publish-draft');   // affiliate drafts (21 Nov 2026)
    Route::post('/listings/{listing}/reject', [ListingController::class, 'reject'])->name('listings.reject');
    Route::post('/listings/{listing}/recommend-rejection', [ListingController::class, 'recommendRejection'])->name('listings.recommend');
    Route::post('/listings/{listing}/pause', [ListingController::class, 'pause'])->name('listings.pause');
    Route::post('/listings/{listing}/archive', [ListingController::class, 'archive'])->name('listings.archive');
    Route::get('/listings/{listing}/test-link', [ListingController::class, 'testLink'])->name('listings.test-link');
    Route::post('/listings/{listing}/check-link', [ListingController::class, 'checkLink'])->middleware('throttle:30,1')->name('listings.check-link');
    Route::post('/recommendations/{recommendation}/decide', [RecommendationController::class, 'decide'])->middleware('can:decide-recommendations')->name('recommendations.decide');

    // Licence checks
    Route::get('/licences', [LicenceController::class, 'index'])->name('licences.index');
    Route::post('/licences/register', [LicenceController::class, 'registerUrl'])->name('licences.register');
    Route::post('/licences/{listing}/verify', [LicenceController::class, 'verify'])->name('licences.verify');
    Route::post('/licences/{listing}/reject', [LicenceController::class, 'reject'])->name('licences.reject');
    Route::get('/licences/{listing}/document', [LicenceController::class, 'document'])->name('licences.document');

    // Deeplink monitor
    Route::post('/deeplinks/hosts', [DeeplinkController::class, 'allowedHosts'])->name('deeplinks.hosts');
    Route::post('/deeplinks/{listing}/recheck', [DeeplinkController::class, 'recheck'])->middleware('throttle:30,1')->name('deeplinks.recheck');
    Route::post('/deeplinks/{listing}/switch-off', [DeeplinkController::class, 'switchOff'])->name('deeplinks.switch-off');
    Route::post('/deeplinks/{listing}/pause', [DeeplinkController::class, 'pause'])->name('deeplinks.pause');
    Route::post('/deeplinks/{listing}/message', [DeeplinkController::class, 'message'])->name('deeplinks.message');

    // Enquiries (read only)
    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->middleware('can:view,enquiry')->name('enquiries.show');
    Route::post('/enquiries/{enquiry}/spam', [EnquiryController::class, 'markSpam'])->name('enquiries.spam');

    // Owners and agents
    // Owners and agents are listed apart; changing a person's role on their page moves them.
    // Add an owner or agent (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-owners')->group(function () {
        Route::get('/users/add', [\App\Http\Controllers\Admin\OwnerCreateController::class, 'create'])->name('users.create');
        Route::post('/users/add', [\App\Http\Controllers\Admin\OwnerCreateController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/invite', [\App\Http\Controllers\Admin\OwnerCreateController::class, 'invite'])->middleware('throttle:10,1')->name('users.invite');
    });
    // Owner and agent CSV import and export (super admins only, 21 Nov 2026). Before /users/{user}.
    Route::middleware('can:manage-user-csv')->prefix('/users/csv')->name('users.csv')->group(function () {
        $csv = \App\Http\Controllers\Admin\UserCsvController::class;
        Route::get('/', [$csv, 'index']);
        Route::get('/export', [$csv, 'export'])->middleware('throttle:10,1')->name('.export');
        Route::get('/template', [$csv, 'template'])->name('.template');
        Route::post('/', [$csv, 'upload'])->middleware('throttle:10,1')->name('.upload');
        Route::get('/preview/{token}', [$csv, 'preview'])->where('token', '[A-Za-z0-9]{32}')->name('.preview');
        Route::post('/preview/{token}', [$csv, 'apply'])->where('token', '[A-Za-z0-9]{32}')->name('.apply');
    });
    Route::get('/users', [UserController::class, 'index'])->defaults('role', 'owner')->name('users.index');
    Route::get('/agents', [UserController::class, 'index'])->defaults('role', 'agent')->name('agents.index');
    Route::get('/agents/{user}', [UserController::class, 'agent'])->middleware('can:view,user')->name('agents.show');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:view,user')->name('users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:update,user')->name('users.update');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('can:update,user')->name('users.reactivate');
    Route::post('/users/{user}/recommend-suspension', [UserController::class, 'recommendSuspension'])->name('users.recommend');

    // Click reports
    Route::get('/clicks', [ClickController::class, 'index'])->name('clicks.index');
    Route::get('/clicks/events', [ClickController::class, 'events'])->name('clicks.events');
    Route::get('/clicks/export', [ClickController::class, 'export'])->name('clicks.export');

    // Islands and resorts
    Route::get('/islands', [IslandController::class, 'index'])->name('islands.index');
    Route::get('/islands/{island}', [IslandController::class, 'show'])->whereNumber('island')->name('islands.show');
    Route::get('/locations/create', [IslandController::class, 'createLocation'])->middleware('can:manage-islands')->name('locations.create');
    Route::post('/locations/resort', [IslandController::class, 'storeLocationArea'])->middleware('can:manage-islands')->name('locations.area');
    Route::post('/islands', [IslandController::class, 'storeIsland'])->middleware('can:manage-islands')->name('islands.store');
    Route::get('/islands/{island}/edit', [IslandController::class, 'editIsland'])->name('islands.edit');
    Route::put('/islands/{island}', [IslandController::class, 'updateIsland'])->name('islands.update');
    Route::post('/islands/{island}/header', [IslandController::class, 'updateIslandHeader'])->name('islands.header');
    Route::post('/islands/{island}/areas', [IslandController::class, 'storeArea'])->middleware('can:manage-islands')->name('areas.store');
    Route::get('/areas/{area}/edit', [IslandController::class, 'editArea'])->name('areas.edit');
    Route::put('/areas/{area}', [IslandController::class, 'updateArea'])->name('areas.update');

    // Content pages
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/pages/create', [PageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}/edit', [PageController::class, 'edit'])->middleware('can:update,page')->name('pages.edit');
    Route::put('/pages/{page}', [PageController::class, 'update'])->middleware('can:update,page')->name('pages.update');
    Route::post('/markdown-preview', [PageController::class, 'preview'])->middleware('throttle:120,1')->name('pages.preview');

    // Super admin only
    Route::middleware('can:manage-affiliates')->group(function () {
        Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
        Route::get('/affiliates/create', [AffiliateController::class, 'create'])->name('affiliates.create');
        Route::post('/affiliates', [AffiliateController::class, 'store'])->name('affiliates.store');
        Route::get('/affiliates/{affiliate}', [AffiliateController::class, 'show'])->name('affiliates.show');
        Route::get('/affiliates/{affiliate}/deeplinks', [DeeplinkController::class, 'affiliate'])->name('affiliates.deeplinks');
        Route::put('/affiliates/{affiliate}', [AffiliateController::class, 'update'])->name('affiliates.update');
        Route::post('/affiliates/{affiliate}/logo', [AffiliateController::class, 'updateLogo'])->name('affiliates.logo');
        Route::post('/affiliates/{affiliate}/website', [AffiliateController::class, 'updateWebsite'])->name('affiliates.website');
        Route::post('/affiliates/{affiliate}/deactivate', [AffiliateController::class, 'deactivate'])->name('affiliates.deactivate');
        Route::post('/affiliates/{affiliate}/reactivate', [AffiliateController::class, 'reactivate'])->name('affiliates.reactivate');
        Route::post('/affiliates/{affiliate}/invite', [AffiliateController::class, 'invite'])->name('affiliates.invite');
    });

    Route::middleware('can:manage-staff')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::post('/staff/{staff}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
        Route::post('/staff/{staff}/reactivate', [StaffController::class, 'reactivate'])->name('staff.reactivate');
        Route::post('/staff/{staff}/reset-two-factor', [StaffController::class, 'resetTwoFactor'])->name('staff.reset-2fa');
        Route::post('/staff/{staff}/sign-out', [StaffController::class, 'signOut'])->name('staff.sign-out');
        Route::post('/staff/{staff}/invite', [StaffController::class, 'invite'])->name('staff.invite');
    });

    Route::middleware('can:manage-appearance')->prefix('appearance')->name('appearance.')->group(function () {
        Route::get('/', [AppearanceController::class, 'edit'])->name('edit');
        Route::put('/colours', [AppearanceController::class, 'saveColours'])->name('colours');
        Route::post('/logo', [AppearanceController::class, 'saveLogo'])->name('logo');
        Route::post('/favicon', [AppearanceController::class, 'saveFavicon'])->name('favicon');
        Route::post('/footer-image', [AppearanceController::class, 'saveFooterImage'])->name('footer-image');
        Route::put('/markers/{kind}', [AppearanceController::class, 'saveMarker'])->where('kind', 'affiliate|free|paid')->name('marker');
        Route::post('/hero', [AppearanceController::class, 'uploadHero'])->name('hero.upload');
        Route::put('/hero', [AppearanceController::class, 'arrangeHeroes'])->name('hero.arrange');
        Route::delete('/hero/{hero}', [AppearanceController::class, 'removeHero'])->name('hero.remove');
        Route::post('/publish', [AppearanceController::class, 'publish'])->name('publish');
        Route::post('/discard', [AppearanceController::class, 'discard'])->name('discard');
        Route::post('/undo', [AppearanceController::class, 'undo'])->name('undo');
    });

    // Admin area colours (super admins only, with the other look-and-feel settings).
    Route::middleware('can:manage-appearance')->group(function () {
        Route::get('/admin-colours', [\App\Http\Controllers\Admin\AdminColoursController::class, 'edit'])->name('admin-colours.edit');
        Route::put('/admin-colours', [\App\Http\Controllers\Admin\AdminColoursController::class, 'update'])->name('admin-colours.update');
    });

    // Maintenance mode (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-maintenance')->group(function () {
        Route::get('/maintenance', [\App\Http\Controllers\Admin\MaintenanceController::class, 'edit'])->name('maintenance.edit');
        Route::put('/maintenance', [\App\Http\Controllers\Admin\MaintenanceController::class, 'update'])->name('maintenance.update');
    });

    // SEO: sitemap, titles and descriptions, sharing, health check (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-seo')->prefix('seo')->name('seo.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SeoController::class, 'edit'])->name('edit');
        Route::post('/meta', [\App\Http\Controllers\Admin\SeoController::class, 'saveMeta'])->name('meta');
        Route::post('/share', [\App\Http\Controllers\Admin\SeoController::class, 'saveShareImage'])->name('share');
        Route::post('/sitemap', [\App\Http\Controllers\Admin\SeoController::class, 'rebuildSitemap'])->middleware('throttle:10,1')->name('sitemap');
    });

    // Account closures: "Close my account" requests (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-account-closures')->prefix('account-closures')->name('closures.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AccountClosureController::class, 'index'])->name('index');
        Route::post('/{user}/close', [\App\Http\Controllers\Admin\AccountClosureController::class, 'close'])->name('close');
        Route::post('/{user}/decline', [\App\Http\Controllers\Admin\AccountClosureController::class, 'decline'])->name('decline');
    });

    // PayPal Settings: Just Island Holidays' PayPal details (super admins only, 20 Nov 2026).
    Route::middleware('can:manage-paypal-settings')->group(function () {
        Route::get('/paypal-settings', [\App\Http\Controllers\Admin\PayPalSettingsController::class, 'edit'])->name('paypal-settings.edit');
        Route::put('/paypal-settings', [\App\Http\Controllers\Admin\PayPalSettingsController::class, 'update'])->middleware('throttle:10,1')->name('paypal-settings.update');
    });

    // Property types (super admins only).
    Route::middleware('can:manage-property-types')->prefix('property-types')->name('property-types.')->group(function () {
        Route::get('/', [PropertyTypeController::class, 'index'])->name('index');
        Route::post('/', [PropertyTypeController::class, 'store'])->name('store');
        Route::get('/{type}/edit', [PropertyTypeController::class, 'edit'])->name('edit');
        Route::put('/{type}', [PropertyTypeController::class, 'update'])->name('update');
        Route::delete('/{type}', [PropertyTypeController::class, 'destroy'])->name('destroy');
    });

    // Island page adverts (super admins only).
    Route::middleware('can:manage-ads')->prefix('ads')->name('ads.')->group(function () {
        Route::get('/', [AdController::class, 'index'])->name('index');
        Route::get('/create', [AdController::class, 'create'])->name('create');
        Route::post('/', [AdController::class, 'store'])->name('store');
        Route::get('/{ad}/edit', [AdController::class, 'edit'])->name('edit');
        Route::put('/{ad}', [AdController::class, 'update'])->name('update');
        Route::delete('/{ad}', [AdController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:manage-plans')->group(function () {
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/header', [PlanController::class, 'updateHeader'])->name('plans.header');
        Route::post('/plans/grant', [PlanController::class, 'grant'])->name('plans.grant');
        Route::get('/subscriptions/{subscription}', [PlanController::class, 'subscription'])->name('subscriptions.show');
        Route::post('/payments/{payment}/refunded', [PlanController::class, 'markRefunded'])->name('payments.refunded');
    });

    Route::middleware('can:view-audit-log')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');
    });
});
