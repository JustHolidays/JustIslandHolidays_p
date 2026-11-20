<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HeroImage;
use App\Models\Island;
use App\Models\Listing;
use App\Models\SiteSetting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(string $lang): View
    {
        $islands = Island::active()
            ->withCount(['listings' => fn ($q) => $q->live()])
            ->orderBy('sort_order')
            ->get();

        $publicListings = fn () => Listing::live()
            ->whereIn('island_id', $islands->pluck('id'))
            ->with(['island', 'area', 'translations', 'amenities:id,code']);

        $featured = $publicListings()
            ->where('featured_until', '>', now())
            ->orderByDesc('rank_score')->orderBy('id')
            ->limit(6)->get();

        $recent = $publicListings()
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(8)->get();

        return view('public.home', [
            'islands' => $islands,
            'featured' => $featured,
            'recent' => $recent,
            'heroes' => $heroes = $this->heroImages(),
            'hero' => $heroes->first(),
        ]);
    }

    /**
     * The hero images in the order they are shown (20 Nov 2026: they cross-fade from one to the
     * next). "Random" on the Frontend Styler starts the slideshow on a random image each visit;
     * "first" always starts on the first.
     *
     * @return \Illuminate\Support\Collection<int, HeroImage>
     */
    private function heroImages(): \Illuminate\Support\Collection
    {
        $heroes = \App\Support\Appearance::heroes()->values();
        if ($heroes->count() < 2 || \App\Support\Appearance::heroMode() === 'first') {
            return $heroes;
        }
        $start = random_int(0, $heroes->count() - 1);

        return $heroes->slice($start)->concat($heroes->slice(0, $start))->values();
    }
}
