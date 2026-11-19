@extends('layouts.admin', ['title' => $affiliate->name])

@section('content')
    <p><a href="{{ route('admin.affiliates.index') }}" class="inline-flex min-h-11 items-center underline">Back to affiliates</a></p>
    <h1 class="text-2xl font-semibold">{{ $affiliate->name }}</h1>
    <p class="mt-1">{{ $affiliate->email }} · {{ $affiliate->isDeactivated() ? 'Deactivated' : 'Active' }} · last sign-in {{ $affiliate->last_login_at?->format('j M Y') ?? 'never' }}</p>
    @php
        $brokenLinks = $listings->whereNotNull('deeplink_url')->where('deeplink_status', 'broken')->count();
    @endphp
    <p class="mt-3"><a href="{{ route('admin.affiliates.deeplinks', $affiliate) }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 bg-white px-4 font-medium">Deeplinks{{ $brokenLinks ? " ({$brokenLinks} broken)" : '' }}</a></p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="l-heading">
            <h2 id="l-heading" class="text-lg font-semibold">Listings</h2>
            <table class="mt-2 w-full text-left text-sm">
                <caption class="sr-only">Listings and clicks in the last 30 days</caption>
                <thead><tr class="border-b"><th scope="col" class="p-2">Listing</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2 text-right">Clicks, 30 days</th></tr></thead>
                <tbody>
                    @forelse ($listings as $l)
                        <tr class="border-b border-slate-100"><td class="p-2"><a href="{{ route('admin.listings.show', $l) }}" class="underline">{{ $l->title('en') }}</a></td><td class="p-2">{{ $l->status->label() }}</td><td class="p-2 text-right">{{ $l->clicks_30 }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-2">No listings yet. <a href="{{ route('admin.import.create') }}" class="underline">Add one from a web address</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2 class="mt-6 text-lg font-semibold">Commission</h2>
            @if ($commission->isEmpty())
                <p class="mt-2 text-sm">No commission reports imported yet.</p>
            @else
                <ul class="mt-2 text-sm">
                    @foreach ($commission as $row)
                        <li>{{ ucfirst($row->status) }}: {{ $row->n }} booking(s), {{ $row->currency === 'GBP' ? '£' : '€' }}{{ number_format($row->total / 100, 2) }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="d-heading">
            <h2 id="d-heading" class="text-lg font-semibold">Details and private notes</h2>
            <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}" class="mt-3">
                @csrf @method('PUT')
                @include('admin.affiliates.fields')
                <x-button>Save</x-button>
            </form>
        </section>
    </div>

    <section class="mt-6 flex flex-wrap items-start gap-3" aria-label="Account">
        <form method="POST" action="{{ route('admin.affiliates.invite', $affiliate) }}">@csrf<button class="min-h-11 rounded border border-slate-400 bg-white px-4">Send a new invitation</button></form>
        @if ($affiliate->isDeactivated())
            <form method="POST" action="{{ route('admin.affiliates.reactivate', $affiliate) }}">@csrf<button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Reactivate</button></form>
        @else
            <x-admin.reason-action :action="route('admin.affiliates.deactivate', $affiliate)" label="Deactivate affiliate" confirm="Deactivate this affiliate? All their listings are paused." />
        @endif
    </section>
@endsection
