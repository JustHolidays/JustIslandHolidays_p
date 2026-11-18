@extends('layouts.admin', ['title' => 'Edit '.$plan->name])

@section('content')
    <p><a href="{{ route('admin.plans.index') }}" class="inline-flex min-h-11 items-center underline">Back to plans</a></p>
    <h1 class="text-2xl font-semibold">{{ $plan->name }} <span class="text-base font-normal text-slate-600">({{ $plan->code }}, for {{ $plan->audience }}s)</span></h1>

    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <x-field name="name" label="Name" :value="$plan->name" required />
        <x-field name="max_listings" label="Listings allowed (empty for unlimited)" type="number" min="1" :value="$plan->max_listings" />
        <x-field name="search_boost" label="Search ranking boost" type="number" min="0" :value="$plan->search_boost" hint="Added to each listing's ranking score." required />
        @foreach (['is_featured' => 'Featured on the home and island pages', 'show_direct_contact' => 'Show the owner\'s phone and website', 'is_active' => 'On sale'] as $f => $l)
            <div class="mb-2 flex min-h-11 items-center gap-2"><input type="hidden" name="{{ $f }}" value="0"><input id="{{ $f }}" type="checkbox" name="{{ $f }}" value="1" class="h-5 w-5" @checked(old($f, $plan->$f))><label for="{{ $f }}">{{ $l }}</label></div>
        @endforeach
        <div class="mb-4"><label for="currency" class="mb-1 block font-medium">Currency</label>
            <select id="currency" name="currency" class="min-h-11 rounded border border-slate-400 bg-white px-2">@foreach (['EUR' => 'Euro', 'GBP' => 'Pound'] as $c => $l)<option value="{{ $c }}" @selected($plan->currency === $c)>{{ $l }}</option>@endforeach</select></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="price_month" label="Monthly price (empty if not offered)" type="number" min="0" step="0.01" :value="$plan->price_month_cents !== null ? number_format($plan->price_month_cents / 100, 2, '.', '') : null" />
            <x-field name="paypal_plan_id_month" label="PayPal plan id, monthly" :value="$plan->paypal_plan_id_month" />
            <x-field name="price_year" label="Yearly price (empty if not offered)" type="number" min="0" step="0.01" :value="$plan->price_year_cents !== null ? number_format($plan->price_year_cents / 100, 2, '.', '') : null" />
            <x-field name="paypal_plan_id_year" label="PayPal plan id, yearly" :value="$plan->paypal_plan_id_year" />
        </div>
        <p class="mb-4 text-sm">The price here is what visitors see. The amount actually charged is set on the plan in PayPal: keep them the same.</p>
        <x-button>Save</x-button>
    </form>
@endsection
