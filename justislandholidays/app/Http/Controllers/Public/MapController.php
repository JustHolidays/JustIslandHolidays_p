<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Search\ListingSearch;
use App\Search\SearchFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/map?lang=en&bbox=south,west,north,east plus the search filters.
 * Pins for the homes in the map view, as light JSON. Only public data: the offset
 * coordinates, never listing_private. Stateless (no session, no cookies).
 */
class MapController extends Controller
{
    public function __invoke(Request $request, ListingSearch $search): JsonResponse
    {
        $lang = $request->query('lang');
        $lang = is_string($lang) && in_array($lang, config('site.enabled_languages'), true) ? $lang : 'en';
        app()->setLocale($lang);

        $result = $search->pins(SearchFilters::fromRequest($request), $this->bbox($request->query('bbox')), $lang);

        $pins = $result['pins']->map(fn (Listing $l) => [
            'ref' => $l->public_ref,
            'kind' => $l->markerKind(),
            'title' => $l->title($lang),
            'place' => $l->area->translate('name').', '.$l->island->translate('name'),
            'price' => $l->guidePrice(),
            'image' => $l->image_path ? $l->imageUrl(400) : null,
            'url' => $l->publicUrl($lang),
            'lat' => (float) $l->display_lat,
            'lng' => (float) $l->display_lng,
        ])->values();

        return response()->json([
            'pins' => $pins,
            'total' => $result['total'],
            'more' => $result['total'] > $pins->count(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    private function bbox(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^-?\d{1,3}(\.\d+)?(,-?\d{1,3}(\.\d+)?){3}$/', $value)) {
            return null;
        }
        [$south, $west, $north, $east] = array_map('floatval', explode(',', $value));

        if ($south > $north || $west > $east || $south < -90 || $north > 90 || $west < -180 || $east > 180) {
            return null;
        }

        return [$south, $west, $north, $east];
    }
}
