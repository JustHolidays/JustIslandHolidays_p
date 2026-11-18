@extends('layouts.account', ['title' => 'Plan and billing'])

@php($money = fn ($cents, $currency) => ($currency === 'GBP' ? '£' : '€').number_format($cents / 100, $cents % 100 ? 2 : 0))

@section('content')
    <h1 class="text-2xl font-semibold">Plan and billing</h1>

    <section class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="current-heading">
        <h2 id="current-heading" class="text-lg font-semibold">Your plan: {{ $currentPlan->name }}</h2>
        @if ($isAgency)<p class="text-sm text-slate-600">This plan covers your whole agency.</p>@endif

        @if ($subscription)
            <p class="mt-2">
                @if ($subscription->status === 'active')
                    Paid {{ $subscription->billing_interval === 'year' ? 'yearly' : 'monthly' }} through PayPal.
                    @if ($subscription->next_billing_at) Next payment: {{ $subscription->next_billing_at->format('j F Y') }}. @endif
                @else
                    Cancelled. Your plan stays active until {{ $subscription->ends_at?->format('j F Y') }}.
                @endif
            </p>
            <p class="mt-2 text-sm">Manage your payment method and see receipts in your
                <a href="https://www.paypal.com/myaccount/autopay/" class="underline" rel="noopener">PayPal account</a>.</p>
            @if ($subscription->status === 'active')
                <form method="POST" action="{{ route('account.billing.cancel') }}" class="mt-4" onsubmit="return confirm('Cancel your plan? It stays active until the end of the period you have paid for.')">
                    @csrf
                    <button type="submit" class="min-h-11 rounded border border-slate-400 px-4">Cancel plan</button>
                </form>
            @endif
        @else
            <p class="mt-2">You are not paying for a plan. Guests can still find your listing and send you enquiries.</p>
        @endif
    </section>

    <section class="mt-8" aria-labelledby="plans-heading">
        <h2 id="plans-heading" class="text-lg font-semibold">{{ $subscription ? 'Change plan' : 'Choose a plan' }}</h2>

        @if ($plans->isEmpty())
            <p class="mt-2">Paid plans are not available to buy yet. Please check back soon.</p>
        @elseif (! $paypalReady)
            <p class="mt-2">Online payment is not set up yet. Please contact us to change your plan.</p>
        @endif

        <ul class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach ($plans as $plan)
                <li class="rounded-lg border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-semibold">{{ $plan->name }} @if ($plan->id === $currentPlan->id)<span class="ml-2 rounded bg-green-100 px-2 py-1 text-sm">Your plan</span>@endif</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        <li>{{ $plan->max_listings === null ? 'Unlimited listings' : trans_choice(':count listing|Up to :count listings', $plan->max_listings) }}</li>
                        @if ($plan->search_boost > 0)<li>Higher in search results</li>@endif
                        @if ($plan->is_featured)<li>Featured on the home and island pages</li>@endif
                        @if ($plan->show_direct_contact)<li>Your phone and website shown</li>@endif
                    </ul>
                    <p class="mt-2 text-sm">No VAT is charged.</p>

                    <form method="POST" action="{{ route('account.billing.checkout') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="plan" value="{{ $plan->code }}">
                        <fieldset @disabled(! $paypalReady)>
                            <legend class="sr-only">How often to pay for {{ $plan->name }}</legend>
                            @foreach (['year' => [$plan->price_year_cents, $plan->paypal_plan_id_year, 'a year'], 'month' => [$plan->price_month_cents, $plan->paypal_plan_id_month, 'a month']] as $interval => [$price, $paypalId, $per])
                                @if ($price !== null)
                                    <div class="flex min-h-11 items-center gap-2">
                                        <input id="{{ $plan->code }}-{{ $interval }}" type="radio" name="interval" value="{{ $interval }}" class="h-5 w-5" @checked($loop->first) @disabled(! $paypalId)>
                                        <label for="{{ $plan->code }}-{{ $interval }}">{{ $money($price, $plan->currency) }} {{ $per }} @unless ($paypalId)<span class="text-sm text-slate-600">(not available yet)</span>@endunless</label>
                                    </div>
                                @endif
                            @endforeach
                            <button type="submit" class="mt-3 min-h-11 rounded bg-[var(--colour-button)] px-5 font-medium text-white disabled:opacity-60">Continue to PayPal</button>
                        </fieldset>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
