{{--
    Map of the homes matching the current filters. Expects $filters; optional $height (Tailwind class).
    Pins load from /api/map as the map moves. Every home is also in the list, so the map is extra.
--}}
@php
    $query = array_filter(array_merge(
        request()->except(['page', 'view', 'sort', 'island', 'area', 'bbox', 'lang']),
        ['lang' => app()->getLocale(), 'island' => $filters->island?->slug, 'area' => $filters->area?->slug],
    ), fn ($v) => $v !== null && $v !== '' && $v !== []);
    $centre = $filters->area?->centre_lat ? $filters->area : ($filters->island?->centre_lat ? $filters->island : null);
    $zoom = $filters->area?->centre_lat ? 13 : 10;
@endphp
<div data-map="pins" data-api="{{ route('api.map') }}" data-query="{{ http_build_query($query) }}"
     @if ($centre) data-lat="{{ $centre->centre_lat }}" data-lng="{{ $centre->centre_lng }}" data-zoom="{{ $zoom }}" @endif
     data-tiles="{{ config('site.map.tile_url') }}" data-attribution="{{ config('site.map.attribution') }}"
     data-status="map-status"
     data-text-count="{{ __(':count homes on the map') }}"
     data-text-more="{{ __('Showing :shown of :total homes. Zoom in to see the rest.') }}"
     data-text-error="{{ __('The map could not load. The homes are listed below.') }}"
     data-text-from="{{ __('From :price a week') }}"
     role="region" aria-label="{{ __('Map of matching homes') }}"
     class="{{ $height ?? 'h-80' }} overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
    <noscript><p class="p-4">{{ __('Turn on JavaScript to see the map.') }}</p></noscript>
</div>
<p id="map-status" role="status" class="mt-2 text-sm text-slate-700"></p>
<p class="text-sm text-slate-700">{{ __('Pins show the area, not the exact address.') }}</p>
