<?php

namespace App\Http\Controllers\Public;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ListingController extends Controller
{
    /**
     * /{lang}/{island}/{area}/{slug}-{ref}. The ref finds the listing, so an old slug,
     * island or resort in the URL gets a 301 to the current address.
     * Not live: 404. Archived or deleted: 410. listing_private is never loaded here.
     */
    public function show(string $lang, string $islandSlug, string $areaSlug, string $slugAndRef): View|RedirectResponse
    {
        $ref = substr($slugAndRef, -8);

        $listing = Listing::withTrashed()
            ->where('public_ref', $ref)
            ->with(['island', 'area', 'translations', 'amenities', 'licence', 'owner', 'photos'])
            ->first();

        abort_if($listing === null, 404);
        abort_if($listing->trashed() || $listing->status === ListingStatus::Archived, 410);
        abort_unless($listing->status === ListingStatus::Live && $listing->island->is_active, 404);

        $canonical = $listing->publicUrl($lang);
        if (request()->url() !== $canonical) {
            return redirect()->to($canonical, 301);
        }

        $similar = Listing::live()
            ->where('island_id', $listing->island_id)
            ->whereKeyNot($listing->id)
            ->orderByRaw('CASE WHEN area_id = ? THEN 0 ELSE 1 END', [$listing->area_id])
            ->orderByRaw('CASE WHEN property_type = ? THEN 0 ELSE 1 END', [$listing->property_type])
            ->orderByDesc('rank_score')
            ->with(['island', 'area', 'translations'])
            ->limit(config('site.similar_count'))
            ->get();

        $isAffiliate = $listing->isAffiliateListing();
        $rates = $listing->hasRates() ? \App\Listings\Rates::upcoming($listing) : collect();
        $calendarMaxOffset = \App\Calendar\Availability::maxPublicOffset(3);   // 18 months, 3 at a time
        $offset = max(0, min($calendarMaxOffset, (int) request()->query('months', 0)));
        $calendarMonths = $listing->hasCalendar()
            ? \App\Calendar\Availability::months($listing, \Carbon\CarbonImmutable::today()->startOfMonth()->addMonths($offset), 3)
            : null;

        // Bookmark menu under the title (owner and agent homes, 20 Nov 2026): only the sections this home has.
        $bookmarks = $isAffiliate ? [] : array_filter([
            'key-facts' => __('Key facts'),
            'rates' => $rates->isNotEmpty() ? __('Rates') : null,
            'availability' => $calendarMonths ? __('Availability') : null,
            'features' => $listing->amenities->isNotEmpty() ? __('Features') : null,
            'location' => __('Location'),
        ]);

        return view('public.listing', [
            'listing' => $listing,
            'similar' => $similar,
            'isAffiliate' => $isAffiliate,
            // Agents' properties say "the agent" instead of "the owner" (20 Nov 2026).
            'isAgent' => $listing->owner?->role === \App\Enums\Role::Agent,
            'directContact' => \App\Listings\DirectContact::for($listing),
            'sidebarAdverts' => \App\Ads\IslandAdverts::sidebarFor($listing->island),   // Sidebar Section
            // Rates table (paid plans): periods that have not ended yet.
            'rates' => $rates,
            // Booking calendar (paid plans): 3 months at a time, up to 18 months ahead.
            'calendarOffset' => $offset,
            'calendarMaxOffset' => $calendarMaxOffset,
            'calendarMonths' => $calendarMonths,
            'bookmarks' => $bookmarks,
            'canonical' => $canonical,
            'alternates' => collect(config('site.enabled_languages'))
                ->mapWithKeys(fn ($l) => [$l => $listing->publicUrl($l)])->all(),
        ]);
    }
}
