{{--
    Menu Advert Section: four banners below the introduction on island and resort pages.
    Expects $adverts (four entries, Ad or null). Each banner is 75 pixels tall; a space with no
    running advert shows the placeholder, which is not a link. Two side by side on phones,
    four from tablet width up.
--}}
<section class="mt-8" aria-label="{{ __('Adverts') }}" data-advert-section="menu">
    <p class="mb-2 text-xs uppercase tracking-wide text-slate-600" aria-hidden="true">{{ __('Advertisements') }}</p>
    <ul class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($adverts as $position => $ad)
            <li data-advert="{{ $position + 1 }}">
                @if ($ad)
                    <a href="{{ route('ads.click', $ad->id) }}" rel="sponsored nofollow noopener" target="_blank" class="block rounded focus-visible:outline-offset-4">
                        <img src="{{ \App\Ads\IslandAdverts::imageUrl($ad) }}" srcset="{{ \App\Ads\IslandAdverts::imageSrcset($ad) }}"
                             alt="{{ __('Advert: :text (opens in a new tab)', ['text' => $ad->alt]) }}" width="300" height="75" loading="lazy"
                             class="h-[75px] w-full rounded object-cover">
                    </a>
                @else
                    <img src="{{ \App\Ads\IslandAdverts::placeholderUrl() }}" srcset="{{ \App\Ads\IslandAdverts::placeholderSrcset() }}"
                         alt="{{ __('Advertise here') }}" width="300" height="75" loading="lazy"
                         class="h-[75px] w-full rounded object-cover">
                @endif
            </li>
        @endforeach
    </ul>
</section>
