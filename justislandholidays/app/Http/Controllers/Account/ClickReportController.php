<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Listings\PageViews;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Views and clicks (20 Nov 2026; was Clicks): for each of the lister's properties, page views
 * and clicks on its link button, for the last 30, 60 or 365 days or all time. Bots are left
 * out of both. Agents also see their agency's properties.
 */
class ClickReportController extends Controller
{
    public const PERIODS = ['30' => 'Last 30 days', '60' => 'Last 60 days', '365' => 'Last 365 days', 'all' => 'All time'];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30';
        $since = $period === 'all' ? null : now()->subDays((int) $period)->startOfDay();

        $listings = Listing::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)
                ->when($user->agency_id, fn ($q) => $q->orWhere('agency_id', $user->agency_id)))
            ->where('status', '!=', 'archived')
            ->with('translations')
            ->get()
            ->keyBy('id');

        $clicks = DB::table('deeplink_clicks')
            ->whereIn('listing_id', $listings->keys())
            ->where('is_bot', false)
            ->when($since, fn ($q) => $q->where('clicked_at', '>=', $since))
            ->groupBy('listing_id')
            ->selectRaw('listing_id, COUNT(*) as clicks, SUM(CASE WHEN is_unique = 1 THEN 1 ELSE 0 END) as unique_clicks')
            ->get()
            ->keyBy('listing_id');

        // Raw clicks are kept 13 months; older ones live on as daily totals (all time only).
        if ($period === 'all') {
            $older = DB::table('deeplink_clicks_daily')
                ->whereIn('listing_id', $listings->keys())
                ->where('day', '<', DB::table('deeplink_clicks')->min('clicked_at') ?? now()->toDateString())
                ->groupBy('listing_id')
                ->selectRaw('listing_id, SUM(clicks) as clicks, SUM(unique_clicks) as unique_clicks')
                ->get()
                ->keyBy('listing_id');
        }

        $views = PageViews::totals($listings->keys(), $since?->toDateString());

        $rows = $listings->map(fn (Listing $l) => [
            'listing' => $l,
            'views' => $views[$l->id] ?? 0,
            'clicks' => (int) ($clicks[$l->id]->clicks ?? 0) + (int) (($older ?? collect())[$l->id]->clicks ?? 0),
            'unique' => (int) ($clicks[$l->id]->unique_clicks ?? 0) + (int) (($older ?? collect())[$l->id]->unique_clicks ?? 0),
        ])->sortByDesc('views')->values();

        return view('account.clicks', [
            'rows' => $rows,
            'since' => $since,
            'period' => $period,
            'periods' => self::PERIODS,
        ]);
    }
}
