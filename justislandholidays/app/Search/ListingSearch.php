<?php

namespace App\Search;

use App\Models\Island;
use App\Models\Listing;
use App\Support\Database\MySql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The one place public search runs. Used by the island, resort and search pages.
 * Only live listings on active islands are ever returned.
 */
final class ListingSearch
{
    public function run(SearchFilters $filters, ?string $lang = null): SearchResult
    {
        $lang ??= app()->getLocale();

        $base = $this->baseQuery($filters, $lang);

        // Facet: counts per property type, with every filter applied except the type filter itself.
        $typeCounts = (clone $base)
            ->select('property_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('property_type')
            ->pluck('aggregate', 'property_type')
            ->map(fn ($n) => (int) $n)
            ->all();

        $query = (clone $base)->when($filters->types !== [], fn (Builder $q) => $q->whereIn('property_type', $filters->types));

        $featured = $this->featured(clone $query, $filters->page);

        $listings = $this->sorted($query, $filters->sort)
            ->with(['island', 'area', 'translations'])
            ->paginate(config('site.per_page'), ['*'], 'page', $filters->page)
            ->withQueryString();

        return new SearchResult($listings, $featured, $typeCounts);
    }

    private function baseQuery(SearchFilters $f, string $lang): Builder
    {
        $query = Listing::query()
            ->live()
            ->whereIn('island_id', Island::active()->select('id'));

        if ($f->island) {
            $query->where('island_id', $f->island->id);
        }
        if ($f->area) {
            $query->where('area_id', $f->area->id);
        }
        if ($f->sleeps !== null) {
            $query->where('sleeps', '>=', $f->sleeps);
        }
        if ($f->bedrooms !== null) {
            $query->where('bedrooms', '>=', $f->bedrooms);
        }
        if ($f->priceMin !== null) {
            $query->where('min_price_week_cents', '>=', $f->priceMin * 100);
        }
        if ($f->priceMax !== null) {
            $query->where('min_price_week_cents', '<=', $f->priceMax * 100);
        }
        if ($f->pets) {
            $query->where('pets_allowed', true);
        }
        if ($f->pool) {
            $query->where('has_private_pool', true);
        }
        // Every selected amenity must match: one EXISTS check each.
        foreach ($f->amenities as $code) {
            $query->whereHas('amenities', fn (Builder $q) => $q->where('code', $code));
        }
        if ($f->text !== null) {
            $this->applyText($query, $f->text, $lang);
        }

        return $query;
    }

    /** Full-text in the visitor's language with an English fallback, plus a title prefix match for partial names. */
    private function applyText(Builder $query, string $text, string $lang): void
    {
        $langs = array_values(array_unique([$lang, 'en']));
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [], fn ($w) => mb_strlen($w) >= 2);

        $query->where(function (Builder $q) use ($text, $words, $langs) {
            $q->whereIn('id', function ($sub) use ($text, $langs) {
                $sub->select('listing_id')->from('listing_translations')
                    ->whereIn('lang', $langs)
                    ->where('title', 'like', addcslashes($text, '%_\\').'%');
            });

            if ($words === []) {
                return;
            }

            $q->orWhereIn('id', function ($sub) use ($words, $langs) {
                $sub->select('listing_id')->from('listing_translations')->whereIn('lang', $langs);

                if (MySql::isMySql()) {
                    $boolean = implode(' ', array_map(fn ($w) => '+'.$w.'*', $words));
                    $sub->whereRaw('MATCH (title, summary) AGAINST (? IN BOOLEAN MODE)', [$boolean]);
                } else {
                    foreach ($words as $w) {
                        $like = '%'.addcslashes($w, '%_\\').'%';
                        $sub->where(fn ($w2) => $w2->where('title', 'like', $like)->orWhere('summary', 'like', $like));
                    }
                }
            });
        });
    }

    private function sorted(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderByRaw('min_price_week_cents IS NULL')->orderBy('min_price_week_cents')->orderBy('id'),
            'price_desc' => $query->orderByRaw('min_price_week_cents IS NULL')->orderByDesc('min_price_week_cents')->orderBy('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'sleeps' => $query->orderByDesc('sleeps')->orderByDesc('rank_score')->orderBy('id'),
            default => $query->orderByDesc('rank_score')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Up to featured_per_page listings with a current featured period, shown first and labelled.
     * They rotate from page to page so every featured listing gets a turn.
     */
    private function featured(Builder $query, int $page)
    {
        $per = (int) config('site.featured_per_page');
        $all = $query->where('featured_until', '>', now())
            ->orderByDesc('rank_score')->orderBy('id')
            ->with(['island', 'area', 'translations'])
            ->limit(50)
            ->get();

        if ($all->count() <= $per) {
            return $all->values();
        }

        $start = (($page - 1) * $per) % $all->count();

        return $all->concat($all)->slice($start, $per)->values();
    }
}
