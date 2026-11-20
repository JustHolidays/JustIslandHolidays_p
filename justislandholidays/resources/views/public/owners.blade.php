@extends('layouts.public', [
    'metaTitle' => __('List your holiday home | Just Island Holidays'),
    'metaDescription' => __('Advertise your holiday home in Mallorca or Menorca. Guests contact you directly, with no booking fees.'),
    'canonical' => route('owners'),
    'seoKeys' => 'owners',
])

@section('content')
    {{-- A bright band to open the page (white text on the brand colour). --}}
    <section class="bg-[var(--colour-brand)] text-white" aria-labelledby="owners-heading">
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h1 id="owners-heading" class="text-3xl font-semibold md:text-4xl">{{ __('List your holiday home') }}</h1>
            <p class="mt-3 max-w-2xl text-lg">{{ __('Guests find your home here and contact you directly. We take no bookings and no commission from you.') }}</p>
            <p class="mt-6"><a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded bg-orange-700 px-6 font-semibold text-white shadow hover:bg-orange-800">{{ __('Create an account') }}</a></p>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-10">
        <section aria-labelledby="how-heading">
            <h2 id="how-heading" class="text-2xl font-semibold">{{ __('How it works') }}</h2>
            <ol class="mt-5 grid gap-4 md:grid-cols-4">
                @foreach ([
                    __('Create an account and confirm your email address.'),
                    __('Add your home: the main details, your photos and your tourist licence number.'),
                    __('We check your licence number in the official register and approve your listing, usually within 48 hours.'),
                    __('Guests send enquiries straight to your inbox.'),
                ] as $i => $step)
                    <li class="rounded-lg border border-slate-200 bg-[var(--colour-band)] p-5">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-700 text-lg font-semibold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                        <p class="mt-3">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>
            <p class="mt-4 text-sm">{{ __('You are responsible for holding a valid tourist licence for your property.') }}</p>
        </section>

        <section class="mt-12" aria-labelledby="plans-heading">
            <h2 id="plans-heading" class="text-2xl font-semibold">{{ __('Plans') }}</h2>
            <ul class="mt-5 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($plans as $plan)
                    @php
                        $image = $plan->headerImageUrl();
                        $periods = $plan->isSoldByPeriod() ? $plan->periodPrices() : [];
                    @endphp
                    <li class="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" data-plan="{{ $plan->code }}">
                        <img src="{{ $image ?: \App\Support\PlanImage::placeholderUrl() }}" alt="{{ $image ? $plan->header_image_alt : '' }}" width="800" height="300" loading="lazy" class="aspect-[8/3] w-full object-cover">
                        <div class="flex-1 p-5">
                            <h3 class="text-xl font-semibold">{{ $plan->name }}</h3>
                            <p class="text-sm text-slate-600">{{ $plan->audience === 'agent' ? __('For agents') : __('For owners') }}</p>
                            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                                <li>{{ $plan->max_listings === null ? __('Unlimited listings') : trans_choice(':count listing|Up to :count listings', $plan->max_listings) }}</li>
                                <li>{{ $plan->audience === 'agent'
                                        ? trans_choice(':count photo per property|Up to :count photos per property', $plan->photoLimit())
                                        : trans_choice(':count photo|Up to :count photos', $plan->photoLimit()) }}</li>
                                @if ($plan->search_boost > 0)<li>{{ __('Higher in search results') }}</li>@endif
                                @if ($plan->is_featured)<li>{{ __('Featured on the home and island pages') }}</li>@endif
                                <li>{{ $plan->show_direct_contact ? __('Your phone and website shown') : __('Enquiry form only') }}</li>
                                @if ($plan->has_booking_calendar)<li>{{ __('Booking calendar') }}</li>@endif
                            </ul>
                        </div>
                        {{-- Price footer: white on orange-700 (5.2 to 1). --}}
                        <div class="bg-orange-700 px-5 py-3 text-white" data-plan-price>
                            @if ($plan->isSoldByPeriod())
                                @if ($periods === [])
                                    <p class="font-semibold">{{ __('Price to be confirmed') }}</p>
                                @else
                                    <p class="text-sm">{{ __('One payment, from the day you buy:') }}</p>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($periods as $months => $cents)
                                            <li class="font-semibold">{{ __(':months months: :price', ['months' => $months, 'price' => $plan->money($cents)]) }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @elseif ($plan->code === 'free' || $plan->price_year_cents === 0)
                                <p class="text-lg font-semibold">{{ __('Free') }}</p>
                            @elseif ($plan->price_year_cents !== null)
                                <p class="text-lg font-semibold">{{ __(':price for 1 year', ['price' => $plan->money($plan->price_year_cents)]) }}</p>
                            @else
                                <p class="font-semibold">{{ __('Price to be confirmed') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="mt-10"><a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded bg-[var(--colour-button)] px-6 font-medium text-white">{{ __('Create an account') }}</a></p>
    </div>
@endsection
