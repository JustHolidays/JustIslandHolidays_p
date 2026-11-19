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
            ->with(['island', 'area', 'translations', 'amenities', 'licence', 'owner'])
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

        return view('public.listing', [
            'listing' => $listing,
            'similar' => $similar,
            'isAffiliate' => $listing->isAffiliateListing(),
            'directContact' => \App\Listings\DirectContact::for($listing),
            'sidebarAdverts' => \App\Ads\IslandAdverts::sidebarFor($listing->island),   // Sidebar Section
            'canonical' => $canonical,
            'alternates' => collect(config('site.enabled_languages'))
                ->mapWithKeys(fn ($l) => [$l => $listing->publicUrl($l)])->all(),
        ]);
    }
}
