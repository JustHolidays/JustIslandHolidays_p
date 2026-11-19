@php
    $place = $area ?? $island;
    $placeName = $place->translate('name');
    $pageUrl = $area ? route('areas.show', [$island->slug, $area->slug]) : route('islands.show', $island->slug);
    $intro = $place->translate('intro');
    $breadcrumbs = array_values(array_filter([
        ['name' => 'Just Island Holidays', 'url' => route('home')],
        ['name' => $island->translate('name'), 'url' => route('islands.show', $island->slug)],
        $area ? ['name' => $placeName, 'url' => $pageUrl] : null,
    ]));
    // Built here, not inline: Blade would read '@context' as a directive.
    $breadcrumbJson = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($breadcrumbs)->values()->map(fn ($b, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $b['name'], 'item' => $b['url']])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp

@extends('layouts.public', [
    'metaTitle' => __('Holiday homes in :place | Just Island Holidays', ['place' => $placeName]),
    'metaDescription' => __('Villas, apartments and fincas to rent in :place. Contact the owners directly.', ['place' => $placeName]),
    'canonical' => $pageUrl,
    'noindex' => ! $filters->isIndexable(),
    'extraScripts' => ['resources/js/map.js'],
])

@push('head')
    <script type="application/ld+json">{!! $breadcrumbJson !!}</script>
@endpush

@section('content')
    @php
        $heading = __('Holiday homes in :place', ['place' => $placeName]);
    @endphp

    @unless ($area)
        {{-- Island pages: the heading and the resorts sit over the header image, with no breadcrumb.
             At least 400 pixels tall from tablet width up (240 on phones), growing if there are many
             resorts. The dark layer keeps white text readable on any photo. Until an admin adds a
             photo, a plain placeholder (no alt text: it shows nothing real). --}}
        <div class="relative isolate w-full overflow-hidden bg-slate-700" data-header-image="{{ $island->header_image_path ? 'photo' : 'placeholder' }}">
            @if ($island->header_image_path)
                <img src="{{ \App\Support\HeaderImage::url($island) }}" srcset="{{ \App\Support\HeaderImage::srcset($island) }}" sizes="100vw"
                     alt="{{ $island->header_image_alt }}" width="2400" height="400" fetchpriority="high"
                     class="absolute inset-0 -z-10 h-full w-full object-cover">
            @else
                <img src="{{ \App\Support\HeaderImage::placeholderUrl() }}" srcset="{{ \App\Support\HeaderImage::placeholderSrcset() }}" sizes="100vw"
                     alt="" width="2400" height="400" fetchpriority="high"
                     class="absolute inset-0 -z-10 h-full w-full object-cover">
            @endif
            <div class="absolute inset-0 -z-10 bg-black/55" aria-hidden="true"></div>

            <div class="mx-auto flex min-h-60 max-w-6xl flex-col justify-end px-4 py-8 text-white sm:min-h-[400px]">
                <h1 class="text-3xl font-semibold md:text-4xl">{{ $heading }}</h1>

                @if ($areas->isNotEmpty())
                    <section class="mt-6" aria-labelledby="areas-heading">
                        <h2 id="areas-heading" class="text-xl font-semibold">{{ __('Resorts and towns') }}</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($areas as $a)
                                <li>
                                    <a href="{{ route('areas.show', [$island->slug, $a->slug]) }}" class="inline-flex min-h-11 items-center rounded-full border border-white bg-white px-4 text-[var(--colour-text)] hover:border-[var(--colour-brand)] hover:underline">
                                        {{ $a->translate('name') }} <span class="ml-1 text-sm text-slate-600">({{ $a->listings_count }})</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    @endunless

    <div class="mx-auto max-w-6xl px-4 py-8">
        @if ($area)
            {{-- Resort pages keep their breadcrumb and plain heading. --}}
            <nav aria-label="{{ __('Breadcrumb') }}" class="mb-4 text-sm">
                <ol class="flex flex-wrap gap-2">
                    @foreach ($breadcrumbs as $crumb)
                        <li>
                            @if (! $loop->last)
                                <a href="{{ $crumb['url'] }}" class="inline-flex min-h-11 items-center underline">{{ $crumb['name'] }}</a> <span aria-hidden="true">/</span>
                            @else
                                <span aria-current="page">{{ $crumb['name'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            <h1 class="text-3xl font-semibold">{{ $heading }}</h1>
        @endif

        @if ($intro)
            <div class="prose {{ $area ? 'mt-4' : '' }} max-w-3xl">{!! \Illuminate\Support\Str::markdown($intro, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
        @endif

        <section class="mt-8" aria-labelledby="map-heading">
            <h2 id="map-heading" class="text-xl font-semibold">{{ __('Map') }}</h2>
            <div class="mt-3">@include('public.partials.map-pins')</div>
        </section>

        <div class="mt-8 grid gap-8 lg:grid-cols-[18rem_1fr]">
            <div>@include('public.partials.filters', ['action' => $pageUrl])</div>
            <div>@include('public.partials.results')</div>
        </div>
    </div>
@endsection
