<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Check availability clicks on the lister's own listings, last 30 days. Bots are left out. */
class ClickReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $since = now()->subDays(30)->startOfDay();

        $listings = Listing::where('owner_id', $user->id)
            ->whereNotNull('deeplink_url')
            ->with('translations')
            ->get()
            ->keyBy('id');

        $totals = DB::table('deeplink_clicks')
            ->whereIn('listing_id', $listings->keys())
            ->where('is_bot', false)
            ->where('clicked_at', '>=', $since)
            ->groupBy('listing_id')
            ->selectRaw('listing_id, COUNT(*) as clicks, SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) as unique_clicks')
            ->get()
            ->keyBy('listing_id');

        $rows = $listings->map(fn (Listing $l) => [
            'listing' => $l,
            'clicks' => (int) ($totals[$l->id]->clicks ?? 0),
            'unique' => (int) ($totals[$l->id]->unique_clicks ?? 0),
        ])->sortByDesc('clicks')->values();

        return view('account.clicks', ['rows' => $rows, 'since' => $since]);
    }
}
