@extends('layouts.public', [
    'metaTitle' => __('Holiday homes in Mallorca and Menorca | Just Island Holidays'),
    'metaDescription' => __('Find villas, apartments and fincas in Mallorca and Menorca, and contact the owners directly.'),
    'canonical' => route('home'),
    'seoKeys' => 'home',
])

@section('content')
    <section class="relative bg-[var(--colour-band)]">
        @if ($hero)
            {{-- Hero slideshow (20 Nov 2026): the images sit on top of each other and app.js
                 cross-fades from one to the next every 7 seconds, with a pause button. Without
                 script, the first image simply stays. --}}
            <div class="absolute inset-0 isolate" data-hero-slides>
                @foreach ($heroes as $slide)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slide->image_path) }}" srcset="{{ \App\Support\Appearance::heroSrcset($slide) }}" sizes="100vw"
                         alt="{{ $slide->alt_text }}" width="2400" height="900"
                         @if ($loop->first) fetchpriority="high" @else loading="lazy" aria-hidden="true" @endif
                         class="absolute inset-0 h-full w-full object-cover transition-opacity duration-[1600ms] ease-in-out {{ $loop->first ? 'opacity-100' : 'opacity-0' }}"
                         data-hero-slide>
                @endforeach
            </div>
            <div class="absolute inset-0 bg-black/40" aria-hidden="true"></div>
            @if ($heroes->count() > 1)
                <button type="button" class="absolute bottom-3 right-3 z-10 inline-flex min-h-11 items-center gap-2 rounded-full bg-black/55 px-4 text-sm font-medium text-white hover:bg-black/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        data-hero-pause hidden>
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" data-icon-pause><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="hidden h-4 w-4" fill="currentColor" data-icon-play><path d="M8 5v14l11-7z"/></svg>
                    <span data-pause-label>{{ __('Pause pictures') }}</span>
                </button>
            @endif
        @endif
        <div class="relative mx-auto max-w-6xl px-4 py-16 {{ $hero ? 'text-white' : '' }}">
            <h1 class="text-3xl font-semibold md:text-4xl">{{ __('Holiday homes in the Balearic Islands') }}</h1>
            <p class="mt-3 max-w-2xl text-lg">{{ __('Find a villa, apartment or finca and contact the owner directly. No booking fees.') }}</p>

            <form method="GET" action="{{ route('search') }}" class="mt-8 flex max-w-3xl flex-col gap-3 rounded-lg bg-white p-4 text-[var(--colour-text)] md:flex-row md:items-end" role="search">
                <div class="flex-1">
                    <label for="home-island" class="mb-1 block font-medium">{{ __('Island') }}</label>
                    <select id="home-island" name="island" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                        <option value="">{{ __('All islands') }}</option>
                        @foreach ($islands as $island)
                            <option value="{{ $island->slug }}">{{ $island->translate('name') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label for="home-sleeps" class="mb-1 block font-medium">{{ __('Guests') }}</label>
                    <select id="home-sleeps" name="sleeps" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ([2, 4, 6, 8, 10, 12] as $n)
                            <option value="{{ $n }}">{{ $n }}+</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-[2]">
                    <label for="home-q" class="mb-1 block font-medium">{{ __('Name or keyword') }}</label>
                    <input id="home-q" name="q" type="search" class="min-h-11 w-full rounded border border-slate-400 px-3">
                </div>
                <button type="submit" class="min-h-11 rounded bg-[var(--colour-button)] px-6 font-medium text-white">{{ __('Search') }}</button>
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4">
        <section class="mt-12" aria-labelledby="islands-heading">
            <h2 id="islands-heading" class="text-2xl font-semibold">{{ __('Choose an island') }}</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($islands as $island)
                    <li>
                        <a href="{{ route('islands.show', $island->slug) }}" class="flex min-h-11 items-center justify-between rounded-lg border border-slate-200 bg-white p-6 hover:border-[var(--colour-brand)]">
                            <span class="text-xl font-semibold">{{ $island->translate('name') }}</span>
                            <span>{{ trans_choice(':count home|:count homes', $island->listings_count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($featured->isNotEmpty())
            <section class="mt-12" aria-labelledby="featured-heading">
                <h2 id="featured-heading" class="text-2xl font-semibold">{{ __('Featured homes') }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featured as $listing)
                        <li><x-listing-card :listing="$listing" :featured="true" /></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-12" aria-labelledby="recent-heading">
            <h2 id="recent-heading" class="text-2xl font-semibold">{{ __('Recently added') }}</h2>
            @if ($recent->isEmpty())
                <p class="mt-4">{{ __('New homes are being added. Please check back soon.') }}</p>
            @else
                <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($recent as $listing)
                        <li><x-listing-card :listing="$listing" /></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
