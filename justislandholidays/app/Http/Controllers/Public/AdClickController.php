<?php

namespace App\Http\Controllers\Public;

use App\Ads\IslandAdverts;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;

/**
 * /api/ads/{ad}/click: counts the click for today in ad_stats_daily, then sends the visitor
 * to the advertiser's page. Stateless (no session or cookies) and stores nothing about the
 * visitor. An advert that is no longer running sends them back to its island page.
 */
class AdClickController extends Controller
{
    public function __invoke(string $ad): RedirectResponse
    {
        // Stateless route: no route-model binding middleware here, so look it up directly.
        $ad = Ad::with('island')->findOrFail((int) $ad);

        if (! IslandAdverts::isRunning($ad)) {
            return $ad->island && $ad->island->is_active
                ? redirect()->route('islands.show', ['lang' => 'en', 'island' => $ad->island->slug])
                : redirect('/');
        }

        IslandAdverts::countClick($ad);

        return redirect()->away($ad->target_url)->withHeaders(['Referrer-Policy' => 'strict-origin-when-cross-origin']);
    }
}
