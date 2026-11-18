@extends('layouts.admin', ['title' => 'Subscription'])

@section('content')
    <p><a href="{{ route('admin.plans.index') }}" class="inline-flex min-h-11 items-center underline">Back to plans and subscriptions</a></p>
    <h1 class="text-2xl font-semibold">{{ $subscription->agency?->name ?? $subscription->user?->name }}: {{ $subscription->plan->name }}</h1>
    <p class="mt-1">{{ str_replace('_', ' ', ucfirst($subscription->status)) }} · {{ $subscription->paypal_subscription_id ?? 'granted free of charge' }} · started {{ $subscription->created_at->format('j M Y') }}
        @if ($subscription->ends_at) · ends {{ $subscription->ends_at->format('j M Y') }} @endif</p>

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="pay-heading">
        <h2 id="pay-heading" class="text-lg font-semibold">Payments</h2>
        <p class="mt-1 text-sm">Refunds are made in PayPal. Record them here if PayPal did not tell us.</p>
        <table class="mt-2 w-full text-left text-sm">
            <caption class="sr-only">Payments</caption>
            <thead><tr class="border-b"><th scope="col" class="p-2">Date</th><th scope="col" class="p-2">Amount</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2">PayPal transaction</th><th scope="col" class="p-2"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                @forelse ($subscription->payments as $p)
                    <tr class="border-b border-slate-100 align-top">
                        <td class="p-2">{{ $p->paid_at?->format('j M Y') }}</td>
                        <td class="p-2">{{ $p->currency === 'GBP' ? '£' : '€' }}{{ number_format($p->amount_cents / 100, 2) }}</td>
                        <td class="p-2">{{ ucfirst($p->status) }}</td>
                        <td class="p-2 font-mono">{{ $p->paypal_transaction_id }}</td>
                        <td class="p-2">@if ($p->status === 'completed')<x-admin.reason-action :action="route('admin.payments.refunded', $p)" label="Record a refund" hint="For example: refunded in PayPal on 3 May at the owner's request." :danger="false" />@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-2">No payments recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
