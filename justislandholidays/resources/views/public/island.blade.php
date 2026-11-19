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
    <div class="mx-auto max-w-6xl px-4 py-8">
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

        <h1 class="text-3xl font-semibold">{{ __('Holiday homes in :place', ['place' => $placeName]) }}</h1>

        @if ($intro)
            <div class="prose mt-4 max-w-3xl">{!! \Illuminate\Support\Str::markdown($intro, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
        @endif

        @if ($areas->isNotEmpty())
            <section class="mt-8" aria-labelledby="areas-heading">
                <h2 id="areas-heading" class="text-xl font-semibold">{{ __('Resorts and towns') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($areas as $a)
                        <li>
                            <a href="{{ route('areas.show', [$island->slug, $a->slug]) }}" class="inline-flex min-h-11 items-center rounded-full border border-slate-300 bg-white px-4 hover:border-[var(--colour-brand)]">
                                {{ $a->translate('name') }} <span class="ml-1 text-sm text-slate-600">({{ $a->listings_count }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
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
