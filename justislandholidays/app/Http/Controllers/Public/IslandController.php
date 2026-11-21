<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Area;
use App\Models\Island;
use App\Search\ListingSearch;
use App\Search\SearchFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Island and resort landing pages: editable intro text plus filtered listings. */
class IslandController extends Controller
{
    public function show(Request $request, ListingSearch $search, string $lang, Island $island): View
    {
        abort_unless($island->is_active, 404);

        $filters = SearchFilters::fromRequest($request, $island);

        return view('public.island', [
            'island' => $island,
            'area' => null,
            // Only resorts with at least one live home (21 Nov 2026); empty ones are not shown.
            'areas' => $island->areas()
                ->withCount(['listings' => fn ($q) => $q->live()])
                ->orderBy('sort_order')->get()
                ->filter(fn ($a) => $a->listings_count > 0)->values(),
            'filters' => $filters,
            'result' => $search->run($filters),
            'amenities' => $this->filterAmenities(),
            'adverts' => \App\Ads\IslandAdverts::menuFor($island),   // Menu Advert Section
        ]);
    }

    public function area(Request $request, ListingSearch $search, string $lang, Island $island, string $areaSlug): View
    {
        abort_unless($island->is_active, 404);
        $area = Area::where('island_id', $island->id)->where('slug', $areaSlug)->firstOrFail();

        $filters = SearchFilters::fromRequest($request, $island, $area);

        return view('public.island', [
            'island' => $island,
            'area' => $area,
            'areas' => collect(),
            'filters' => $filters,
            'result' => $search->run($filters),
            'amenities' => $this->filterAmenities(),
            'adverts' => \App\Ads\IslandAdverts::menuFor($island),   // Menu Advert Section: the resort's island
        ]);
    }

    private function filterAmenities()
    {
        return Amenity::where('is_filterable', true)->orderBy('sort_order')->get();
    }
}
