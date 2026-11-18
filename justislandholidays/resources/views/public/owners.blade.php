@extends('layouts.public', [
    'metaTitle' => __('List your holiday home | Just Island Holidays'),
    'metaDescription' => __('Advertise your holiday home in Mallorca or Menorca. Guests contact you directly, with no booking fees.'),
    'canonical' => route('owners'),
])

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ __('List your holiday home') }}</h1>
        <p class="mt-4 text-lg">{{ __('Guests find your home here and contact you directly. We take no bookings and no commission from you.') }}</p>

        <section class="mt-8" aria-labelledby="how-heading">
            <h2 id="how-heading" class="text-xl font-semibold">{{ __('How it works') }}</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>{{ __('Create an account and confirm your email address.') }}</li>
                <li>{{ __('Add your home: the main details, one photo and your tourist licence number.') }}</li>
                <li>{{ __('We check your licence number in the official register and approve your listing, usually within 48 hours.') }}</li>
                <li>{{ __('Guests send enquiries straight to your inbox.') }}</li>
            </ol>
            <p class="mt-4 text-sm">{{ __('You are responsible for holding a valid tourist licence for your property.') }}</p>
        </section>

        <section class="mt-8" aria-labelledby="plans-heading">
            <h2 id="plans-heading" class="text-xl font-semibold">{{ __('Plans') }}</h2>
            <ul class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($plans as $plan)
                    <li class="rounded-lg border border-slate-200 bg-white p-5">
                        <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $plan->audience === 'agent' ? __('For agents') : __('For owners') }}</p>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                            <li>{{ $plan->max_listings === null ? __('Unlimited listings') : trans_choice(':count listing|Up to :count listings', $plan->max_listings) }}</li>
                            @if ($plan->search_boost > 0)<li>{{ __('Higher in search results') }}</li>@endif
                            @if ($plan->is_featured)<li>{{ __('Featured on the home and island pages') }}</li>@endif
                            <li>{{ $plan->show_direct_contact ? __('Your phone and website shown') : __('Enquiry form only') }}</li>
                        </ul>
                        <p class="mt-3 font-semibold">
                            @if ($plan->price_year_cents === null && $plan->price_month_cents === null)
                                {{ $plan->code === 'free' ? __('Free') : __('Price to be confirmed') }}
                            @else
                                @if ($plan->price_year_cents !== null){{ __(':price a year', ['price' => ($plan->currency === 'GBP' ? '£' : '€').number_format($plan->price_year_cents / 100)]) }}@endif
                                @if ($plan->price_month_cents !== null) · {{ __(':price a month', ['price' => ($plan->currency === 'GBP' ? '£' : '€').number_format($plan->price_month_cents / 100)]) }}@endif
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="mt-8"><a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded bg-[var(--colour-button)] px-6 font-medium text-white">{{ __('Create an account') }}</a></p>
    </div>
@endsection
