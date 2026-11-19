@php
    // List or map view, kept in the address so it can be bookmarked and the filters keep it.
    $mapView = request()->query('view') === 'map';
    $listUrl = request()->fullUrlWithQuery(['view' => null, 'page' => null]);
    $mapUrl = request()->fullUrlWithQuery(['view' => 'map', 'page' => null]);
    $tab = 'inline-flex min-h-11 items-center rounded px-4 font-medium';
@endphp

@extends('layouts.public', [
    'metaTitle' => __('Search holiday homes | Just Island Holidays'),
    'canonical' => route('search'),
    'noindex' => true,
    'extraScripts' => $mapView ? ['resources/js/map.js'] : [],
])

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ __('Search holiday homes') }}</h1>

        <div class="mt-8 grid gap-8 lg:grid-cols-[18rem_1fr]">
            <div>@include('public.partials.filters', ['action' => route('search')])</div>
            <div>
                <nav aria-label="{{ __('Show results as') }}" class="mb-4">
                    <ul class="inline-flex gap-1 rounded-lg border border-slate-300 bg-white p-1">
                        <li><a href="{{ $listUrl }}" @if (! $mapView) aria-current="page" @endif
                               class="{{ $tab }} {{ $mapView ? 'underline' : 'bg-[var(--colour-button)] text-white' }}">{{ __('List') }}</a></li>
                        <li><a href="{{ $mapUrl }}" @if ($mapView) aria-current="page" @endif
                               class="{{ $tab }} {{ $mapView ? 'bg-[var(--colour-button)] text-white' : 'underline' }}">{{ __('Map') }}</a></li>
                    </ul>
                </nav>

                @if ($mapView)
                    <p class="mb-4 font-medium">{{ trans_choice(':count home found|:count homes found', $result->total()) }}</p>
                    @include('public.partials.map-pins', ['height' => 'h-[32rem]'])
                    <p class="mt-4"><a href="{{ $listUrl }}" class="inline-flex min-h-11 items-center underline">{{ __('See these homes as a list') }}</a></p>
                @else
                    @include('public.partials.results')
                @endif
            </div>
        </div>
    </div>
@endsection
