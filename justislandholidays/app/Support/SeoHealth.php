<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * The SEO health check on Settings › SEO (20 Nov 2026): things that may hold pages back in
 * search results, each with a link to where it is fixed. Live properties on active islands only.
 */
final class SeoHealth
{
    public const SHORT_SUMMARY = 80;

    public const LIMIT = 50;

    /**
     * @return list<array{key: string, title: string, why: string, items: Collection<int, array{label: string, url: string}>}>
     */
    public static function checks(): array
    {
        $live = Listing::live()->whereHas('island', fn ($q) => $q->where('is_active', true))->with('translations')->get();
        $item = fn (Listing $l) => ['label' => $l->title('en').' ('.$l->public_ref.')', 'url' => route('admin.listings.show', $l)];

        $islands = Island::active()->orderBy('sort_order')->get();
        $areas = Area::whereIn('island_id', $islands->pluck('id'))->with('island')->orderBy('sort_order')->get();

        $titles = $live->groupBy(fn (Listing $l) => mb_strtolower(trim($l->title('en'))))->filter(fn ($g) => $g->count() > 1);

        return [
            [
                'key' => 'short_summary', 'title' => 'Short summaries',
                'why' => 'Summaries under '.self::SHORT_SUMMARY.' characters give search results little to show.',
                'items' => $live->filter(fn ($l) => mb_strlen((string) $l->summary('en')) < self::SHORT_SUMMARY)->map($item)->values(),
            ],
            [
                'key' => 'no_photo', 'title' => 'No main photo',
                'why' => 'Homes without a photo do poorly in search and when shared.',
                'items' => $live->whereNull('image_path')->map($item)->values(),
            ],
            [
                'key' => 'no_alt', 'title' => 'Main photo without a description',
                'why' => 'Search engines read the photo description (alt text); it also helps people who cannot see the photo.',
                'items' => $live->whereNotNull('image_path')->filter(fn ($l) => blank($l->image_alt))->map($item)->values(),
            ],
            [
                'key' => 'duplicate_titles', 'title' => 'Titles used more than once',
                'why' => 'Search engines may show only one of several pages with the same title.',
                'items' => $titles->flatten(1)->map($item)->values(),
            ],
            [
                'key' => 'no_intro', 'title' => 'Islands and resorts without an introduction',
                'why' => 'A few lines about the place give its page something of its own.',
                'items' => $islands->filter(fn ($i) => blank($i->translate('intro', 'en')))
                    ->map(fn ($i) => ['label' => $i->translate('name', 'en'), 'url' => route('admin.islands.edit', $i)])
                    ->concat($areas->filter(fn ($a) => blank($a->translate('intro', 'en')))
                        ->map(fn ($a) => ['label' => $a->translate('name', 'en').', '.$a->island->translate('name', 'en'), 'url' => route('admin.areas.edit', $a)]))
                    ->values(),
            ],
        ];
    }
}
