@props(['listing', 'featured' => false, 'headingLevel' => 3])

@php
    $title = $listing->title();
    $tag = 'h'.$headingLevel;
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">
    <div class="relative aspect-[4/3] bg-slate-100">
        @if ($listing->imageUrl())
            <img src="{{ $listing->imageUrl(800) }}" @if ($listing->imageSrcset()) srcset="{{ $listing->imageSrcset() }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" @endif
                 alt="{{ $listing->image_alt ?: $title }}" loading="lazy" width="400" height="300" class="h-full w-full object-cover">
        @else
            <div class="flex h-full items-center justify-center text-sm text-slate-600" role="img" aria-label="{{ __('No photo yet') }}">{{ __('No photo yet') }}</div>
        @endif
        @if ($featured)
            <span class="absolute left-2 top-2 rounded bg-white px-2 py-1 text-xs font-semibold text-[var(--colour-brand)]">{{ __('Featured') }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-4">
        <{{ $tag }} class="text-lg font-semibold">
            <a href="{{ $listing->publicUrl() }}" class="hover:underline">{{ $title }}</a>
        </{{ $tag }}>
        <p class="text-sm text-slate-700">{{ $listing->area->translate('name') }}, {{ $listing->island->translate('name') }}</p>
        <p class="mt-2 text-sm">
            {{ \App\Models\PropertyType::label($listing->property_type) }} ·
            {{ trans_choice(':count bedroom|:count bedrooms', $listing->bedrooms) }} ·
            {{ __('sleeps :count', ['count' => $listing->sleeps]) }}
        </p>
    </div>
    {{-- Guide price as an orange bar across the foot of the card (white on orange-700 is 5.2 to 1). --}}
    @if ($listing->guidePrice())
        <p class="bg-orange-700 px-4 py-2 font-semibold text-white" data-card-price>{{ __('From :price a week', ['price' => $listing->guidePrice()]) }}</p>
    @endif
</article>
