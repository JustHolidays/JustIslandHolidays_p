<?php

namespace App\Http\Controllers\Public;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\DeeplinkClick;
use App\Models\Listing;
use App\Support\IpHasher;
use App\Support\VisitorAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * /go/{ref}: records a Check availability click, then redirects to the deeplink.
 * Runs without the web middleware group, so it sets no cookies and starts no session.
 * A switched-off or broken link sends the visitor to the listing page instead.
 */
class GoController extends Controller
{
    private const SOURCES = ['home', 'island', 'area', 'search', 'listing', 'other'];

    public function __invoke(Request $request, string $ref): RedirectResponse
    {
        $listing = Listing::where('public_ref', $ref)->with(['island', 'area'])->first();

        abort_if($listing === null || $listing->status !== ListingStatus::Live || ! $listing->island->is_active, 404);

        $lang = in_array($request->query('l'), config('site.enabled_languages'), true) ? $request->query('l') : 'en';

        if (! $listing->showsCheckAvailability()) {
            return $this->redirect($listing->publicUrl($lang));
        }

        $ipHash = IpHasher::hash($request->ip());
        $clickId = (string) Str::ulid();
        $target = $this->withClickId($listing->deeplink_url, $listing->deeplink_subid_param, $clickId);

        $rateKey = 'go:'.$ipHash;
        RateLimiter::hit($rateKey, 60);
        $overLimit = RateLimiter::attempts($rateKey) > (int) config('site.clicks.per_ip_per_minute');
        $isBot = $overLimit || VisitorAgent::isBot($request);

        $isUnique = ! $isBot && ! DeeplinkClick::where('ip_hash', $ipHash)
            ->where('listing_id', $listing->id)
            ->where('is_bot', false)
            ->where('clicked_at', '>=', now()->subMinutes((int) config('site.clicks.unique_window_minutes')))
            ->exists();

        DeeplinkClick::create([
            'click_id' => $clickId,
            'listing_id' => $listing->id,
            'affiliate_id' => $listing->owner_id,
            'island_id' => $listing->island_id,
            'clicked_at' => now(),
            'lang' => $lang,
            'source_page' => in_array($request->query('s'), self::SOURCES, true) ? $request->query('s') : 'other',
            'device' => VisitorAgent::device($request->userAgent()),
            'ip_hash' => $ipHash,
            'is_bot' => $isBot,
            'is_unique' => $isUnique,
            'target_url' => $target,
        ]);

        return $this->redirect($target);
    }

    /** Adds our click id where the programme supports a sub-id parameter, keeping any #fragment. */
    private function withClickId(string $url, ?string $param, string $clickId): string
    {
        if ($param === null || $param === '') {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $base .= (str_contains($base, '?') ? '&' : '?').rawurlencode($param).'='.rawurlencode($clickId);

        return $fragment !== null ? $base.'#'.$fragment : $base;
    }

    private function redirect(string $url): RedirectResponse
    {
        return redirect()->away($url, 302)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }
}
