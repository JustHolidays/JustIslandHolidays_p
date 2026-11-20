@php
    [$translation, $isFallback] = $listing->translationFor();
    $title = $listing->title();
    $summary = $listing->summary();
    $place = $listing->area->translate('name').', '.$listing->island->translate('name');
    $licence = $listing->licence;
    $distance = $listing->distance_to_beach_m;
    $structured = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'VacationRental',
        'name' => $title,
        'description' => $summary,
        'image' => $listing->imageUrl(),
        'url' => $canonical,
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $listing->area->translate('name'), 'addressRegion' => $listing->island->translate('name'), 'addressCountry' => 'ES'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $listing->display_lat, 'longitude' => (float) $listing->display_lng],
        'containsPlace' => ['@type' => 'Accommodation', 'numberOfBedrooms' => $listing->bedrooms, 'occupancy' => ['@type' => 'QuantitativeValue', 'maxValue' => $listing->sleeps]],
    ]);
@endphp

@extends('layouts.public', [
    'metaTitle' => $title.' – '.$place.' | Just Island Holidays',
    'metaDescription' => $summary ? \Illuminate\Support\Str::limit($summary, 155) : null,
    'canonical' => $canonical,
    'alternates' => $alternates,
    'extraScripts' => $isAffiliate ? ['resources/js/map.js'] : ['resources/js/map.js', 'resources/js/altcha.js'],
])

@push('head')
    <script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <x-breadcrumb :items="[
            ['name' => __('Home'), 'url' => route('home')],
            ['name' => $listing->island->translate('name'), 'url' => route('islands.show', $listing->island->slug)],
            ['name' => $listing->area->translate('name'), 'url' => route('areas.show', [$listing->island->slug, $listing->area->slug])],
            ['name' => $title, 'url' => null],
        ]" />

        <h1 class="text-3xl font-semibold">{{ $title }}</h1>
        <p class="mt-1 text-lg">{{ $place }}</p>
        @if ($isFallback)
            <p class="mt-2 inline-block rounded bg-slate-100 px-2 py-1 text-sm">{{ __('Shown in English') }}</p>
        @endif

        <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div>
                <div class="aspect-[4/3] overflow-hidden rounded-lg bg-slate-100">
                    @if ($listing->imageUrl())
                        <img src="{{ $listing->imageUrl() }}" @if ($listing->imageSrcset()) srcset="{{ $listing->imageSrcset() }}" sizes="(min-width: 1024px) 66vw, 100vw" @endif
                             alt="{{ $listing->image_alt ?: $title }}" width="1200" height="900" fetchpriority="high" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-slate-600" role="img" aria-label="{{ __('No photo yet') }}">{{ __('No photo yet') }}</div>
                    @endif
                </div>

                {{-- More photos, up to the plan's limit. Each opens the large copy. --}}
                @php
                    $morePhotos = $listing->visiblePhotos();
                @endphp
                @if ($morePhotos->isNotEmpty())
                    <section class="mt-3" aria-label="{{ __('More photos') }}">
                        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($morePhotos as $extra)
                                <li data-gallery-photo>
                                    <a href="{{ $extra->url(1600) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded">
                                        <img src="{{ $extra->url(400) }}" alt="{{ __(':alt (opens the large photo)', ['alt' => $extra->alt]) }}" width="400" height="300" loading="lazy" class="aspect-[4/3] w-full object-cover hover:opacity-90">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($summary)
                    <p class="mt-6 text-lg">{{ $summary }}</p>
                @endif

                <section class="mt-8" aria-labelledby="facts-heading">
                    <h2 id="facts-heading" class="text-xl font-semibold">{{ __('Key facts') }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div><dt class="text-sm text-slate-600">{{ __('Type') }}</dt><dd class="font-medium">{{ \App\Models\PropertyType::label($listing->property_type) }}</dd></div>
                        <div><dt class="text-sm text-slate-600">{{ __('Sleeps') }}</dt><dd class="font-medium">{{ $listing->sleeps }}</dd></div>
                        <div><dt class="text-sm text-slate-600">{{ __('Bedrooms') }}</dt><dd class="font-medium">{{ $listing->bedrooms }}</dd></div>
                        <div><dt class="text-sm text-slate-600">{{ __('Bathrooms') }}</dt><dd class="font-medium">{{ $listing->bathrooms }}</dd></div>
                        <div><dt class="text-sm text-slate-600">{{ __('Private pool') }}</dt><dd class="font-medium">{{ $listing->has_private_pool ? __('Yes') : __('No') }}</dd></div>
                        @if ($listing->pets_allowed !== null)
                            <div data-fact="pets"><dt class="text-sm text-slate-600">{{ __('Pets') }}</dt><dd class="font-medium">{{ $listing->pets_allowed ? __('Allowed') : __('Not allowed') }}</dd></div>
                        @endif
                        @if ($distance !== null)
                            <div><dt class="text-sm text-slate-600">{{ __('To the beach') }}</dt><dd class="font-medium">{{ $distance >= 1000 ? __(':km km', ['km' => round($distance / 1000, 1)]) : __(':m m', ['m' => $distance]) }}</dd></div>
                        @endif
                        @if ($listing->guidePrice())
                            <div><dt class="text-sm text-slate-600">{{ __('From Price') }}</dt><dd class="font-medium">{{ __(':price a week', ['price' => $listing->guidePrice()]) }}</dd></div>
                        @endif
                    </dl>
                    @if ($listing->guidePrice())
                        <p class="mt-3 text-sm text-slate-700">{{ __('Prices are a guide from the owner. The owner confirms the final price and terms.') }}</p>
                    @endif
                </section>

                @if ($rates->isNotEmpty())
                    <section class="mt-8" id="rates" aria-labelledby="rates-heading">
                        <h2 id="rates-heading" class="text-xl font-semibold">{{ __('Rates') }}</h2>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full min-w-[30rem] text-left">
                                <thead>
                                    <tr class="border-b-2 border-slate-300">
                                        <th scope="col" class="py-2 pr-3">{{ __('Dates') }}</th>
                                        <th scope="col" class="py-2 pr-3">{{ __('Per night') }}</th>
                                        <th scope="col" class="py-2 pr-3">{{ __('Per week') }}</th>
                                        <th scope="col" class="py-2">{{ __('14 nights') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rates as $rate)
                                        <tr class="border-b border-slate-200">
                                            <td class="py-2 pr-3">{{ $rate->start_date->translatedFormat('j M Y') }} {{ __('to') }} {{ $rate->end_date->translatedFormat('j M Y') }}</td>
                                            @foreach (['nightly_cents', 'weekly_cents', 'fortnight_cents'] as $column)
                                                <td class="py-2 pr-3">
                                                    @if ($rate->{$column} === null)
                                                        <span aria-hidden="true">–</span><span class="sr-only">{{ __('Not offered') }}</span>
                                                    @else
                                                        {{ \App\Listings\Rates::money($rate->{$column}, $listing->currency) }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ __('Prices are a guide from the owner. The owner confirms the final price and terms.') }}</p>
                    </section>
                @endif

                @if ($calendarMonths)
                    <section class="mt-8" id="availability" aria-labelledby="availability-heading">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="availability-heading" class="text-xl font-semibold">{{ __('Availability') }}</h2>
                            <nav aria-label="{{ __('Calendar months') }}" class="flex gap-2">
                                @if ($calendarOffset > 0)<a href="{{ $canonical }}?months={{ max(0, $calendarOffset - 3) }}#availability" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">{{ __('Earlier months') }}</a>@endif
                                @if ($calendarOffset < 9)<a href="{{ $canonical }}?months={{ $calendarOffset + 3 }}#availability" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">{{ __('Later months') }}</a>@endif
                            </nav>
                        </div>
                        <p class="mt-1 text-sm text-slate-700">{{ __('Kept up to date by the owner. Send an enquiry to check your dates.') }}</p>
                        <div class="mt-3">@include('partials.calendar-months', ['months' => $calendarMonths])</div>
                    </section>
                @endif

                @if ($listing->amenities->isNotEmpty())
                    <section class="mt-8" aria-labelledby="amenities-heading">
                        <h2 id="amenities-heading" class="text-xl font-semibold">{{ __('Features') }}</h2>
                        <ul class="mt-3 grid list-disc gap-1 pl-5 sm:grid-cols-2">
                            @foreach ($listing->amenities->sortBy('sort_order') as $amenity)
                                <li>{{ $amenity->translate('name') }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="mt-8" aria-labelledby="location-heading">
                    <h2 id="location-heading" class="text-xl font-semibold">{{ __('Location') }}</h2>
                    <p class="mt-2">{{ __('The home is inside the circle, in or near :place. You get the exact address when you book.', ['place' => $place]) }}</p>
                    <div data-map="area" data-lat="{{ $listing->display_lat }}" data-lng="{{ $listing->display_lng }}" data-radius="{{ config('site.map.area_radius_m') }}"
                         data-tiles="{{ config('site.map.tile_url') }}" data-attribution="{{ config('site.map.attribution') }}"
                         role="region" aria-label="{{ __('Map showing roughly where the home is') }}"
                         class="mt-3 h-72 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 sm:h-80">
                        <noscript><p class="p-4">{{ __('Turn on JavaScript to see the map.') }}</p></noscript>
                    </div>
                </section>

                @if ($licence && ($licence->registration_number || $licence->national_registry_no))
                    <section class="mt-8" aria-labelledby="licence-heading">
                        <h2 id="licence-heading" class="text-xl font-semibold">{{ __('Tourist licence') }}</h2>
                        <dl class="mt-3 space-y-1">
                            @if ($licence->registration_number)
                                <div><dt class="inline">{{ __('Balearic registration:') }}</dt> <dd class="inline font-mono">{{ $licence->registration_number }}</dd></div>
                            @endif
                            @if ($licence->national_registry_no)
                                <div><dt class="inline">{{ __('National registry number:') }}</dt> <dd class="inline font-mono">{{ $licence->national_registry_no }}</dd></div>
                            @endif
                        </dl>
                    </section>
                @endif
            </div>

            <aside class="space-y-6" aria-label="{{ __('Contact and availability') }}">
                @if ($listing->showsCheckAvailability())
                    <div class="rounded-lg border-2 border-[var(--colour-brand)] bg-white p-5">
                        @if ($isAffiliate)
                            {{-- The affiliate's logo, 100 pixels tall and fitted inside; a placeholder until one is uploaded. Not a link. --}}
                            @php
                                $partnerLogo = \App\Support\AffiliateLogo::url($listing->owner);
                            @endphp
                            <div class="mb-4 flex h-[100px] items-center justify-center" data-partner-logo="{{ $partnerLogo ? 'logo' : 'placeholder' }}">
                                @if ($partnerLogo)
                                    <img src="{{ $partnerLogo }}" alt="{{ $listing->owner->affiliate_logo_alt ?: $listing->owner->name }}" height="100" class="max-h-full max-w-full object-contain">
                                @else
                                    <img src="{{ \App\Support\AffiliateLogo::placeholderUrl() }}" alt="" width="352" height="100" class="max-h-full max-w-full object-contain">
                                @endif
                            </div>
                        @endif
                        <a href="{{ $listing->goUrl('listing') }}" rel="sponsored nofollow" class="flex min-h-11 w-full items-center justify-center rounded bg-[var(--colour-button)] px-5 text-lg font-semibold text-white">{{ __('Check availability') }}</a>
                        <p class="mt-3 text-sm text-slate-700">{{ __('Opens the booking site for this home. We may earn a commission if you book, at no extra cost to you.') }}</p>
                    </div>
                @endif

                {{-- Sidebar Section, directly below Check availability. Homes without a Check
                     availability button show it at the end of the sidebar instead (see below). --}}
                @if ($listing->showsCheckAvailability())
                    @include('public.partials.sidebar-adverts')
                @endif

                @if ($directContact)
                    <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="direct-heading">
                        <h2 id="direct-heading" class="text-xl font-semibold">{{ __('Contact the owner directly') }}</h2>
                        <dl class="mt-3 space-y-2">
                            @if ($directContact['phone'])
                                <div><dt class="text-sm text-slate-600">{{ __('Phone') }}</dt>
                                    <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $directContact['phone']) }}" class="inline-flex min-h-11 items-center underline">{{ $directContact['phone'] }}</a></dd></div>
                            @endif
                            @if ($directContact['website'])
                                <div><dt class="text-sm text-slate-600">{{ __('Website') }}</dt>
                                    <dd><a href="{{ $directContact['website'] }}" rel="sponsored nofollow noopener" class="inline-flex min-h-11 items-center break-all underline">{{ preg_replace('#^https://(www\.)?#', '', rtrim($directContact['website'], '/')) }}</a></dd></div>
                            @endif
                        </dl>
                    </section>
                @endif

                @unless ($isAffiliate)
                    @include('public.partials.enquiry-form')
                @endunless

                @unless ($listing->showsCheckAvailability())
                    @include('public.partials.sidebar-adverts')
                @endunless
            </aside>
        </div>

        @if ($similar->isNotEmpty())
            <section class="mt-12" aria-labelledby="similar-heading">
                <h2 id="similar-heading" class="text-2xl font-semibold">{{ __('Similar homes nearby') }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($similar as $other)
                        <li><x-listing-card :listing="$other" /></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
