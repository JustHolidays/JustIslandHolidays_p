@extends('layouts.admin', ['title' => 'Plans and subscriptions'])

@php
    $money = fn ($cents, $cur) => $cents === null ? '–' : ($cur === 'GBP' ? '£' : '€').number_format($cents / 100, $cents % 100 ? 2 : 0);
    $paypalBase = $paypalMode === 'live' ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com';
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">Plans and subscriptions</h1>
    <p class="mt-1">Prices carry no VAT (Just Island Holidays is not VAT registered). A plan can be bought online once it has a price and a PayPal plan id.</p>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Plans</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Plan</th><th scope="col" class="p-2">For</th><th scope="col" class="p-2">Listings</th><th scope="col" class="p-2">Boost</th><th scope="col" class="p-2">Featured</th><th scope="col" class="p-2">Phone and website</th><th scope="col" class="p-2">Monthly</th><th scope="col" class="p-2">Yearly</th><th scope="col" class="p-2">On sale</th></tr></thead>
            <tbody>
                @foreach ($plans as $p)
                    <tr class="border-b border-slate-200">
                        <td class="p-2"><a href="{{ route('admin.plans.edit', $p) }}" class="font-medium underline">{{ $p->name }}</a> <span class="text-slate-500">{{ $p->code }}</span></td>
                        <td class="p-2">{{ ucfirst($p->audience) }}s</td>
                        <td class="p-2">{{ $p->max_listings ?? 'Unlimited' }}</td>
                        <td class="p-2">{{ $p->search_boost }}</td>
                        <td class="p-2">{{ $p->is_featured ? 'Yes' : 'No' }}</td>
                        <td class="p-2">{{ $p->show_direct_contact ? 'Shown' : 'Hidden' }}</td>
                        <td class="p-2">{{ $money($p->price_month_cents, $p->currency) }} @if ($p->price_month_cents !== null && ! $p->paypal_plan_id_month)<span class="block text-amber-800">no PayPal id</span>@endif</td>
                        <td class="p-2">{{ $money($p->price_year_cents, $p->currency) }} @if ($p->price_year_cents !== null && ! $p->paypal_plan_id_year)<span class="block text-amber-800">no PayPal id</span>@endif</td>
                        <td class="p-2">{{ $p->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="mt-6 max-w-3xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="grant-heading">
        <h2 id="grant-heading" class="text-lg font-semibold">Gift A Plan</h2>
        <p class="mt-1 text-sm">Gives an owner or agent a plan free of charge until a date, for example the Featured Plan for a year for early owners. For an agent in an agency it covers the whole agency.</p>
        <form method="POST" action="{{ route('admin.plans.grant') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf
            <x-field name="email" label="Their account email" type="email" required />
            <div class="mb-4"><label for="plan" class="mb-1 block font-medium">Plan</label>
                <select id="plan" name="plan" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-3">@foreach ($plans->where('is_active', true)->where('code', '!=', 'free') as $p)<option value="{{ $p->code }}">{{ $p->name }} ({{ $p->audience }}s)</option>@endforeach</select></div>
            <x-field name="until" label="Until" type="date" :value="now()->addYear()->toDateString()" required />
            <x-field name="reason" label="Reason (kept in the audit log)" required />
            <div><x-button>Gift plan</x-button></div>
        </form>
    </section>

    <section class="mt-6" aria-labelledby="subs-heading">
        <h2 id="subs-heading" class="text-lg font-semibold">Subscriptions</h2>
        <nav aria-label="Filter subscriptions" class="mt-2"><ul class="flex flex-wrap gap-2">
            @foreach (['' => 'All', 'active' => 'Active', 'approval_pending' => 'Waiting for PayPal', 'cancelled' => 'Cancelled', 'suspended' => 'Suspended', 'expired' => 'Expired'] as $v => $l)
                <li><a href="{{ route('admin.plans.index', array_filter(['status' => $v])) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => request('status', '') === $v, 'border-slate-300' => request('status', '') !== $v])>{{ $l }}</a></li>
            @endforeach
        </ul></nav>
        <div class="mt-3 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Subscriptions, newest first</caption>
                <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Account</th><th scope="col" class="p-2">Plan</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2">Next payment</th><th scope="col" class="p-2">Ends</th><th scope="col" class="p-2">PayPal</th></tr></thead>
                <tbody>
                    @forelse ($subscriptions as $s)
                        <tr class="border-b border-slate-200">
                            <td class="p-2"><a href="{{ route('admin.subscriptions.show', $s) }}" class="underline">{{ $s->agency?->name ?? $s->user?->name }}</a></td>
                            <td class="p-2">{{ $s->plan->name }} ({{ $s->billing_interval === 'year' ? 'yearly' : 'monthly' }})</td>
                            <td class="p-2">{{ str_replace('_', ' ', ucfirst($s->status)) }} @unless ($s->paypal_subscription_id)<span class="block text-slate-600">Granted free</span>@endunless</td>
                            <td class="p-2">{{ $s->next_billing_at?->format('j M Y') ?? '–' }}</td>
                            <td class="p-2">{{ $s->ends_at?->format('j M Y') ?? '–' }}</td>
                            <td class="p-2">@if ($s->paypal_subscription_id)<a href="{{ $paypalBase }}/billing/subscriptions/{{ $s->paypal_subscription_id }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $s->paypal_subscription_id }}</a>@else – @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">No subscriptions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    </section>
@endsection
