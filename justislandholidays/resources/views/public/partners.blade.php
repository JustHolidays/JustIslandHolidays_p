@extends('layouts.public', [
    'metaTitle' => __('Our partners | Just Island Holidays'),
    'metaDescription' => __('The booking partners whose holiday homes you can find on Just Island Holidays.'),
    'canonical' => route('partners'),
    'seoKeys' => 'partners',
])

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ __('Our partners') }}</h1>
        <p class="mt-2 max-w-3xl">{{ __('These booking partners list holiday homes on Just Island Holidays. Click a logo to visit their website. We may earn a commission if you book through them, at no extra cost to you.') }}</p>

        @if ($partners->isEmpty())
            <p class="mt-8">{{ __('Our partners will be listed here soon.') }}</p>
        @else
            <ul class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($partners as $partner)
                    @php
                        $logo = \App\Support\AffiliateLogo::url($partner);
                        $linked = \App\Support\Partners::hasWebsite($partner);
                    @endphp
                    <li class="flex flex-col items-center rounded-lg border border-slate-200 bg-white p-5 text-center" data-partner="{{ $partner->id }}">
                        @if ($linked)
                            <a href="{{ route('partners.go', $partner->id) }}" rel="sponsored nofollow noopener" target="_blank" class="flex h-[100px] w-full items-center justify-center rounded">
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="{{ __(':name (opens their website in a new tab)', ['name' => $partner->affiliate_logo_alt ?: $partner->name]) }}" class="max-h-full max-w-full object-contain" loading="lazy">
                                @else
                                    <img src="{{ \App\Support\AffiliateLogo::placeholderUrl() }}" alt="{{ __(':name (opens their website in a new tab)', ['name' => $partner->name]) }}" class="max-h-full max-w-full object-contain" loading="lazy">
                                @endif
                            </a>
                        @else
                            <div class="flex h-[100px] w-full items-center justify-center">
                                <img src="{{ $logo ?: \App\Support\AffiliateLogo::placeholderUrl() }}" alt="{{ $logo ? ($partner->affiliate_logo_alt ?: $partner->name) : '' }}" class="max-h-full max-w-full object-contain" loading="lazy">
                            </div>
                        @endif
                        <p class="mt-3 font-semibold">{{ $partner->name }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
