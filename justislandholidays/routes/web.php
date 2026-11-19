<?php

use App\Http\Controllers\Account\AccountEnquiryController;
use App\Http\Controllers\Account\BillingController;
use App\Http\Controllers\Account\ClickReportController;
use App\Http\Controllers\Account\ListingEditorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AltchaController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Public\EnquiryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\IslandController;
use App\Http\Controllers\Public\ListingController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Middleware\SetPublicLanguage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/en');

// Proof-of-work challenge for the ALTCHA widget on public forms.
Route::get('/altcha/challenge', AltchaController::class)->middleware('throttle:30,1')->name('altcha.challenge');

/*
| Public site. Every URL starts with a language: /en, /es, /de.
| Languages that are not switched on yet (config/site.php) return 404.
*/
Route::prefix('{lang}')
    ->where(['lang' => implode('|', config('site.languages'))])
    ->middleware(SetPublicLanguage::class)
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/search', SearchController::class)->name('search');
        Route::get('/owners', [PageController::class, 'owners'])->name('owners');
        Route::get('/partners', [\App\Http\Controllers\Public\PartnerController::class, 'index'])->name('partners');
        Route::get('/p/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('pages.show');
        Route::post('/enquiries', [EnquiryController::class, 'store'])->name('enquiries.store');

        Route::get('/{island:slug}', [IslandController::class, 'show'])->where('island', '[a-z0-9-]+')->name('islands.show');
        Route::get('/{island:slug}/{area}', [IslandController::class, 'area'])
            ->where(['island' => '[a-z0-9-]+', 'area' => '[a-z0-9-]+'])->name('areas.show');
        Route::get('/{island}/{area}/{listing}', [ListingController::class, 'show'])
            ->where(['island' => '[a-z0-9-]+', 'area' => '[a-z0-9-]+', 'listing' => '[a-z0-9-]+-[a-z2-9]{8}'])
            ->name('listings.show');
    });

/*
| Account area (owners, agents, affiliates; admins also land here after sign-in).
| Fortify registers the sign-in, sign-up, password and two-factor routes under /account.
*/
// Admins' second sign-in step: an emailed 6-digit code, or their authenticator app.
Route::middleware(['auth'])->prefix('account/verify-sign-in')->name('second-factor.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SecondFactorController::class, 'show'])->name('show');
    Route::post('/', [\App\Http\Controllers\SecondFactorController::class, 'verify'])->middleware('throttle:20,1')->name('verify');
    Route::post('/resend', [\App\Http\Controllers\SecondFactorController::class, 'resend'])->middleware('throttle:5,1')->name('resend');
});

Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Route::get('/security', [AccountController::class, 'security'])->name('security');

    Route::middleware('verified')->group(function () {
        Route::get('/', [AccountController::class, 'home'])->name('home');

        // Owners, agents and affiliates.
        Route::middleware('can:hold-listings')->group(function () {
            Route::get('/listings', [ListingEditorController::class, 'index'])->name('listings.index');
            Route::get('/listings/create', [ListingEditorController::class, 'create'])->name('listings.create');
            Route::post('/listings', [ListingEditorController::class, 'store'])->name('listings.store');

            Route::middleware('can:editAsLister,listing')->group(function () {
                Route::get('/listings/{listing}/edit/{step?}', [ListingEditorController::class, 'edit'])
                    ->where('step', 'basics|location|photo|link|licence|calendar|review')->name('listings.edit');
                Route::put('/listings/{listing}/basics', [ListingEditorController::class, 'updateBasics'])->name('listings.basics');
                Route::put('/listings/{listing}/location', [ListingEditorController::class, 'updateLocation'])->name('listings.location');
                Route::put('/listings/{listing}/photo', [ListingEditorController::class, 'updatePhoto'])->name('listings.photo');
                Route::post('/listings/{listing}/photos', [ListingEditorController::class, 'addPhoto'])->name('listings.photos.store');
                // Booking calendar (paid plans).
                Route::post('/listings/{listing}/calendar/feeds', [\App\Http\Controllers\Account\CalendarController::class, 'addFeed'])->middleware('throttle:10,1')->name('listings.calendar.feeds.store');
                Route::post('/listings/{listing}/calendar/feeds/{feed}/sync', [\App\Http\Controllers\Account\CalendarController::class, 'syncFeed'])->whereNumber('feed')->middleware('throttle:10,1')->name('listings.calendar.feeds.sync');
                Route::delete('/listings/{listing}/calendar/feeds/{feed}', [\App\Http\Controllers\Account\CalendarController::class, 'removeFeed'])->whereNumber('feed')->name('listings.calendar.feeds.destroy');
                Route::post('/listings/{listing}/calendar/bookings', [\App\Http\Controllers\Account\CalendarController::class, 'addBooking'])->name('listings.calendar.bookings.store');
                Route::post('/listings/{listing}/calendar/toggle', [\App\Http\Controllers\Account\CalendarController::class, 'toggleNight'])->name('listings.calendar.toggle');
                Route::delete('/listings/{listing}/calendar/bookings/{booking}', [\App\Http\Controllers\Account\CalendarController::class, 'removeBooking'])->whereNumber('booking')->name('listings.calendar.bookings.destroy');
                Route::delete('/listings/{listing}/photos/{photo}', [ListingEditorController::class, 'removePhoto'])->whereNumber('photo')->name('listings.photos.destroy');
                Route::put('/listings/{listing}/link', [ListingEditorController::class, 'updateLink'])->name('listings.link');
                Route::put('/listings/{listing}/licence', [ListingEditorController::class, 'updateLicence'])->name('listings.licence');
                Route::get('/listings/{listing}/licence-document', [ListingEditorController::class, 'licenceDocument'])->name('listings.licence-document');
                Route::post('/listings/{listing}/status/{action}', [ListingEditorController::class, 'changeStatus'])
                    ->where('action', 'submit|reopen|pause|resume|archive')->name('listings.status');
            });

            Route::get('/enquiries', [AccountEnquiryController::class, 'index'])->name('enquiries.index');
            Route::get('/enquiries/{enquiry}', [AccountEnquiryController::class, 'show'])->middleware('can:view,enquiry')->name('enquiries.show');
            Route::patch('/enquiries/{enquiry}/status', [AccountEnquiryController::class, 'updateStatus'])->middleware('can:updateStatus,enquiry')->name('enquiries.status');

            Route::get('/clicks', ClickReportController::class)->middleware('can:view-own-clicks')->name('clicks');
        });

        // Owners and agents only; affiliates and admins are outside billing.
        Route::middleware('can:use-billing')->group(function () {
            Route::get('/billing', [BillingController::class, 'show'])->name('billing');
            Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
            Route::get('/billing/return', [BillingController::class, 'returned'])->name('billing.return');
            Route::get('/billing/cancelled', [BillingController::class, 'abandoned'])->name('billing.abandoned');
            Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
            Route::post('/billing/featured', [BillingController::class, 'featured'])->name('billing.featured');
            Route::get('/billing/featured/return', [BillingController::class, 'featuredReturned'])->name('billing.featured.return');
        });
    });
});

// Invitations for staff and affiliates (set a password).
Route::middleware('guest')->group(function () {
    Route::get('/account/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/account/invitation', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitation.store');
});

require __DIR__.'/admin.php';
