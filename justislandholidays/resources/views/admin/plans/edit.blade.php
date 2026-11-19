@extends('layouts.admin', ['title' => 'Edit '.$plan->name])

@php
    $money = fn ($cents) => $cents !== null ? number_format($cents / 100, 2, '.', '') : null;
@endphp

@section('content')
    <p><a href="{{ route('admin.plans.index') }}" class="inline-flex min-h-11 items-center underline">Back to plans</a></p>
    <h1 class="text-2xl font-semibold">{{ $plan->name }} <span class="text-base font-normal text-slate-600">({{ $plan->code }}, for {{ $plan->audience }}s)</span></h1>

    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <x-field name="name" label="Name" :value="$plan->name" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="max_listings" label="Listings allowed (empty for unlimited)" type="number" min="1" :value="$plan->max_listings" />
            <x-field name="max_photos" label="Photos per property" type="number" min="1" max="50" :value="$plan->max_photos" hint="Including the main photo." required />
        </div>
        <x-field name="search_boost" label="Search ranking boost" type="number" min="0" :value="$plan->search_boost" hint="Added to each listing's ranking score." required />
        @foreach (['is_featured' => 'Featured on the home and island pages (sold for 3, 6, 9 or 12 months)', 'show_direct_contact' => 'Show the owner\'s phone and website', 'has_booking_calendar' => 'Show "Booking calendar" on the plan card', 'is_active' => 'On sale'] as $f => $l)
            <div class="mb-2 flex min-h-11 items-center gap-2"><input type="hidden" name="{{ $f }}" value="0"><input id="{{ $f }}" type="checkbox" name="{{ $f }}" value="1" class="h-5 w-5" @checked(old($f, $plan->$f))><label for="{{ $f }}">{{ $l }}</label></div>
        @endforeach
        <div class="mb-4"><label for="currency" class="mb-1 block font-medium">Currency</label>
            <select id="currency" name="currency" class="min-h-11 rounded border border-slate-400 bg-white px-2">@foreach (['EUR' => 'Euro', 'GBP' => 'Pound'] as $c => $l)<option value="{{ $c }}" @selected($plan->currency === $c)>{{ $l }}</option>@endforeach</select></div>

        @if ($plan->isSoldByPeriod())
            <fieldset class="mb-4">
                <legend class="font-medium">Price for each period (one-off payment, starts on the day they pay)</legend>
                <div class="mt-2 grid gap-4 sm:grid-cols-4">
                    @foreach (\App\Models\Plan::FEATURED_PERIODS as $months)
                        <x-field :name="'price_'.$months.'m'" :label="$months.' months'" type="number" min="0" step="0.01" :value="$money($plan->{'price_'.$months.'m_cents'})" />
                    @endforeach
                </div>
                <p class="text-sm text-slate-600">Leave a period empty to not offer it. Paid through PayPal as a single payment; it does not renew.</p>
            </fieldset>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="price_year" label="Price for 1 year (empty if free or not on sale)" type="number" min="0" step="0.01" :value="$money($plan->price_year_cents)" />
                <x-field name="paypal_plan_id_year" label="PayPal plan id, yearly" :value="$plan->paypal_plan_id_year" />
            </div>
            <p class="mb-4 text-sm">Plans are sold for a year at a time. The price here is what visitors see; the amount charged is set on the plan in PayPal, so keep them the same.</p>
        @endif
        <x-button>Save</x-button>
    </form>

    @php
        $headerUrl = $plan->headerImageUrl();
    @endphp
    <section class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="card-heading">
        <h2 id="card-heading" class="text-lg font-semibold">Card image</h2>
        <p class="mt-1 text-sm">Shown at the top of this plan's card on the List your holiday home page. Cropped from the centre to 8 by 3. JPG, PNG or WebP, at least 800 × 300 pixels.</p>
        <img src="{{ $headerUrl ?: \App\Support\PlanImage::placeholderUrl() }}" alt="{{ $headerUrl ? $plan->header_image_alt : '' }}" width="800" height="300" class="mt-3 aspect-[8/3] w-full max-w-md rounded object-cover">
        @unless ($headerUrl)<p class="mt-2 text-sm font-medium">The card shows this placeholder until you add an image.</p>@endunless
        <form method="POST" action="{{ route('admin.plans.header', $plan) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
            @csrf
            <label for="header_image" class="block font-medium">{{ $headerUrl ? 'Replace the image (optional)' : 'Image' }}</label>
            <input id="header_image" name="header_image" type="file" accept="image/jpeg,image/png,image/webp" class="block min-h-11" @unless ($headerUrl) required @endunless>
            @error('header_image')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <x-field name="header_image_alt" label="Description (alt text)" :value="$plan->header_image_alt" required />
            <x-button>Save card image</x-button>
        </form>
        @if ($headerUrl)
            <form method="POST" action="{{ route('admin.plans.header', $plan) }}" class="mt-2">@csrf<input type="hidden" name="remove" value="1"><button type="submit" class="min-h-11 rounded border border-red-800 px-4 text-red-800">Remove card image</button></form>
        @endif
    </section>
@endsection
