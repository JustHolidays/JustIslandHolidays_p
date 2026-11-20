<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml (20 Nov 2026): every public page search engines should know about, in each
 * language that is switched on, with the other languages linked. Built at most once an hour
 * (and again after Rebuild now on Settings › SEO); robots.txt points to it.
 */
final class Sitemap
{
    private const CACHE_KEY = 'sitemap.xml';

    private const INFO_KEY = 'sitemap.info';

    public static function xml(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => self::build());
    }

    /** @return array{built_at: ?string, urls: int} */
    public static function info(): array
    {
        return Cache::get(self::INFO_KEY, ['built_at' => null, 'urls' => 0]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): string
    {
        $langs = config('site.enabled_languages');
        $entries = [];
        // One page in every language: each language's address, with all of them as alternates.
        $add = function (callable $url, ?string $lastmod = null, array $images = []) use ($langs, &$entries) {
            $alternates = collect($langs)->mapWithKeys(fn ($l) => [$l => $url($l)])->all();
            foreach ($alternates as $loc) {
                $entries[] = ['loc' => $loc, 'lastmod' => $lastmod, 'alternates' => count($langs) > 1 ? $alternates : [], 'images' => $images];
            }
        };

        $add(fn ($l) => route('home', $l));
        foreach (Island::active()->orderBy('sort_order')->get() as $island) {
            $add(fn ($l) => route('islands.show', [$l, $island->slug]));
            foreach (Area::where('island_id', $island->id)->orderBy('sort_order')->get() as $area) {
                $add(fn ($l) => route('areas.show', [$l, $island->slug, $area->slug]));
            }
        }
        Listing::live()->whereHas('island', fn ($q) => $q->where('is_active', true))
            ->with(['island', 'area'])->orderBy('id')
            ->chunk(500, function ($chunk) use ($add) {
                foreach ($chunk as $listing) {
                    $add(fn ($l) => $listing->publicUrl($l), $listing->updated_at?->toAtomString(), array_filter([$listing->imageUrl(1600)]));
                }
            });
        foreach (Page::whereNull('island_id')->where('is_published', true)->orderBy('slug')->get() as $page) {
            $add(fn ($l) => route('pages.show', [$l, $page->slug]), $page->updated_at?->toAtomString());
        }
        $add(fn ($l) => route('owners', $l));
        $add(fn ($l) => route('partners', $l));

        Cache::forever(self::INFO_KEY, ['built_at' => now()->toDateTimeString(), 'urls' => count($entries)]);

        return view('sitemap', ['entries' => $entries])->render();
    }
}
