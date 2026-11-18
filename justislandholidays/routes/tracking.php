<?php

use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\Public\GoController;
use Illuminate\Support\Facades\Route;

/*
| Stateless routes, loaded WITHOUT the web middleware group (see bootstrap/app.php):
| no session, no cookies, no CSRF token.
*/

// Click tracking: the redirect sets no cookies.
Route::get('/go/{ref}', GoController::class)->where('ref', '[a-z2-9]{8}')->name('go');

// PayPal webhooks: authenticated by PayPal's signature, not a session.
Route::post('/api/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paypal');
