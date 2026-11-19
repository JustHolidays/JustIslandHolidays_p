{{--
    Sidebar Section: affiliate adverts on property pages, below Check availability. Expects
    $sidebarAdverts (every running advert for the property's island, in random order; no limit).
    Banners are 4 wide by 1 tall across the sidebar. With none running, one "Advertise here"
    placeholder shows, which is not a link.
--}}
<section aria-label="{{ __('Adverts') }}" data-advert-section="sidebar">
    <p class="mb-2 text-xs uppercase tracking-wide text-slate-600" aria-hidden="true">{{ __('Advertisements') }}</p>
    <ul class="space-y-3">
        @forelse ($sidebarAdverts as $ad)
            <li data-sidebar-advert>
                <a href="{{ route('ads.click', $ad->id) }}" rel="sponsored nofollow noopener" target="_blank" class="block rounded focus-visible:outline-offset-4">
                    <img src="{{ \App\Ads\IslandAdverts::imageUrl($ad) }}" srcset="{{ \App\Ads\IslandAdverts::imageSrcset($ad) }}"
                         alt="{{ __('Advert: :text (opens in a new tab)', ['text' => $ad->alt]) }}" width="300" height="75" loading="lazy"
                         class="aspect-[4/1] w-full rounded object-cover">
                </a>
            </li>
        @empty
            <li>
                <img src="{{ \App\Ads\IslandAdverts::placeholderUrl() }}" srcset="{{ \App\Ads\IslandAdverts::placeholderSrcset() }}"
                     alt="{{ __('Advertise here') }}" width="300" height="75" loading="lazy" class="aspect-[4/1] w-full rounded object-cover">
            </li>
        @endforelse
    </ul>
</section>
