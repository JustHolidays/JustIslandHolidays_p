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
            ->with(['island', 'area', 'translations']);

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
            'hero' => $this->heroImage(),
        ]);
    }

    /** One image at random on each visit, or the first one only, as set on the Appearance page. */
    private function heroImage(): ?HeroImage
    {
        $heroes = \App\Support\Appearance::heroes();
        if ($heroes->isEmpty()) {
            return null;
        }

        return \App\Support\Appearance::heroMode() === 'first' ? $heroes->first() : $heroes->random();
    }
}
