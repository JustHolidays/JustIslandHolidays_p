<?php

namespace App\Search;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SearchResult
{
    /**
     * @param  LengthAwarePaginator<int, Listing>  $listings
     * @param  Collection<int, Listing>  $featured  labelled "Featured", at most featured_per_page
     * @param  array<string, int>  $typeCounts  property type => number of matches
     */
    public function __construct(
        public readonly LengthAwarePaginator $listings,
        public readonly Collection $featured,
        public readonly array $typeCounts,
    ) {
    }

    public function total(): int
    {
        return $this->listings->total();
    }
}
