@extends('layouts.account', ['title' => 'Plan and billing'])

@section('content')
    <h1 class="text-2xl font-semibold">Plan and billing</h1>

    <section class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="current-heading">
        <h2 id="current-heading" class="text-lg font-semibold">Your plan: {{ $currentPlan->name }}</h2>
        @if ($isAgency)<p class="text-sm text-slate-600">This plan covers your whole agency.</p>@endif

        @if ($subscription)
            <p class="mt-2">
                @if ($subscription->billing_interval === 'one_off')
                    Paid once through PayPal. Active until {{ $subscription->ends_at?->format('j F Y') }}; it does not renew.
                @elseif (! $subscription->paypal_subscription_id)
                    A gift from Just Island Holidays, until {{ $subscription->ends_at?->format('j F Y') }}.
                @elseif ($subscription->status === 'active')
                    Paid {{ $subscription->billing_interval === 'year' ? 'yearly' : 'monthly' }} through PayPal.
                    @if ($subscription->next_billing_at) Next payment: {{ $subscription->next_billing_at->format('j F Y') }}. @endif
                @else
                    Cancelled. Your plan stays active until {{ $subscription->ends_at?->format('j F Y') }}.
                @endif
            </p>
            @if ($subscription->paypal_subscription_id)
                <p class="mt-2 text-sm">Manage your payment method and see receipts in your
                    <a href="https://www.paypal.com/myaccount/autopay/" class="underline" rel="noopener">PayPal account</a>.</p>
            @endif
            @if ($subscription->status === 'active' && $subscription->paypal_subscription_id)
                <form method="POST" action="{{ route('account.billing.cancel') }}" class="mt-4" onsubmit="return confirm('Cancel your plan? It stays active until the end of the year you have paid for.')">
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
                <li class="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white" data-plan="{{ $plan->code }}">
                    <div class="flex-1 p-5">
                        <h3 class="text-lg font-semibold">{{ $plan->name }} @if ($plan->id === $currentPlan->id)<span class="ml-2 rounded bg-green-100 px-2 py-1 text-sm">Your plan</span>@endif</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                            <li>{{ $plan->max_listings === null ? 'Unlimited listings' : trans_choice(':count listing|Up to :count listings', $plan->max_listings) }}</li>
                            <li>{{ trans_choice(':count photo|Up to :count photos', $plan->photoLimit()) }}{{ $plan->audience === 'agent' ? ' per property' : '' }}</li>
                            @if ($plan->search_boost > 0)<li>Higher in search results</li>@endif
                            @if ($plan->is_featured)<li>Featured on the home and island pages</li>@endif
                            @if ($plan->show_direct_contact)<li>Your phone and website shown</li>@endif
                            @if ($plan->has_booking_calendar)<li>Booking calendar</li>@endif
                        </ul>
                        <p class="mt-2 text-sm">No VAT is charged.</p>
                    </div>

                    <div class="border-t border-slate-200 bg-[var(--colour-band)] p-5">
                        @if ($plan->isSoldByPeriod())
                            <form method="POST" action="{{ route('account.billing.featured') }}">
                                @csrf
                                <fieldset @disabled(! $paypalReady)>
                                    <legend class="font-medium">How long for? One payment, starting today. It does not renew.</legend>
                                    @foreach ($plan->periodPrices() as $months => $cents)
                                        <div class="flex min-h-11 items-center gap-2">
                                            <input id="featured-{{ $months }}" type="radio" name="months" value="{{ $months }}" class="h-5 w-5" @checked($loop->first)>
                                            <label for="featured-{{ $months }}">{{ $months }} months: {{ $plan->money($cents) }}</label>
                                        </div>
                                    @endforeach
                                    @error('months')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                    <button type="submit" class="mt-3 min-h-11 rounded bg-orange-700 px-5 font-medium text-white hover:bg-orange-800 disabled:opacity-60">Pay with PayPal</button>
                                </fieldset>
                            </form>
                        @else
                            <form method="POST" action="{{ route('account.billing.checkout') }}">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $plan->code }}">
                                <p class="text-lg font-semibold">{{ $plan->money($plan->price_year_cents) }} for 1 year</p>
                                <p class="text-sm text-slate-700">Renews every year until you cancel.</p>
                                @unless ($plan->paypal_plan_id_year)<p class="text-sm text-slate-600">Not available to buy yet.</p>@endunless
                                <button type="submit" @disabled(! $paypalReady || ! $plan->paypal_plan_id_year) class="mt-3 min-h-11 rounded bg-orange-700 px-5 font-medium text-white hover:bg-orange-800 disabled:opacity-60">Continue to PayPal</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
