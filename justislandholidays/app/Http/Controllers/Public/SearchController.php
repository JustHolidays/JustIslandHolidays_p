<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Island;
use App\Search\ListingSearch;
use App\Search\SearchFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, ListingSearch $search, string $lang): View
    {
        $filters = SearchFilters::fromRequest($request);

        return view('public.search', [
            'filters' => $filters,
            'result' => $search->run($filters),
            'islands' => Island::active()->with('areas')->orderBy('sort_order')->get(),
            'amenities' => Amenity::where('is_filterable', true)->orderBy('sort_order')->get(),
        ]);
    }
}
