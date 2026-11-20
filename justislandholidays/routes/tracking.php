<?php

use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\Public\GoController;
use Illuminate\Support\Facades\Route;

/*
| Stateless routes, loaded WITHOUT the web middleware group (see bootstrap/app.php):
| no session, no cookies, no CSRF token.
*/

// robots.txt: everything blocked before launch (SITE_BLOCK_INDEXING), otherwise only private areas.
Route::get('/robots.txt', \App\Http\Controllers\Public\RobotsController::class)->name('robots');

// Click tracking: the redirect sets no cookies.
Route::get('/go/{ref}', GoController::class)->where('ref', '[a-z2-9]{8}')->name('go');

// Map pins for the island, resort and search maps.
Route::get('/api/map', \App\Http\Controllers\Public\MapController::class)
    ->middleware('throttle:60,1')
    ->name('api.map');

// Booking calendar for owners to put on their own website in an iframe (paid plans).
Route::get('/embed/calendar/{ref}', \App\Http\Controllers\Public\CalendarEmbedController::class)
    ->where('ref', '[a-z2-9]{8}')
    ->middleware('throttle:120,1')
    ->name('calendar.embed');

// Our partners page: a logo click is counted per day, then on to the affiliate's home page.
Route::get('/go/partner/{id}', [\App\Http\Controllers\Public\PartnerController::class, 'go'])
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name('partners.go');

// Advert clicks: counted per day, then on to the advertiser's page.
Route::get('/api/ads/{ad}/click', \App\Http\Controllers\Public\AdClickController::class)
    ->whereNumber('ad')
    ->middleware('throttle:60,1')
    ->name('ads.click');

// PayPal webhooks: authenticated by PayPal's signature, not a session.
Route::post('/api/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paypal');
