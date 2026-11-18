@extends('layouts.account', ['title' => 'Your account'])

@section('content')
    <h1 class="text-2xl font-semibold">Hello, {{ $user->name }}</h1>
    <p class="mt-1">Signed in as {{ $user->role->label() }}.</p>

    @if ($isLister)
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('account.listings.index') }}" class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-[var(--colour-brand)]">
                <p class="text-sm text-slate-600">Live listings</p>
                <p class="text-3xl font-semibold">{{ $listingCounts['live'] ?? 0 }}</p>
                @if (($listingCounts['pending_review'] ?? 0) > 0)
                    <p class="text-sm">{{ $listingCounts['pending_review'] }} waiting for review</p>
                @endif
            </a>
            <a href="{{ route('account.enquiries.index') }}" class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-[var(--colour-brand)]">
                <p class="text-sm text-slate-600">New enquiries</p>
                <p class="text-3xl font-semibold">{{ $newEnquiries }}</p>
                <p class="text-sm">{{ $enquiries30 }} in the last 30 days</p>
            </a>
            @if ($plan)
                <a href="{{ route('account.billing') }}" class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-[var(--colour-brand)]">
                    <p class="text-sm text-slate-600">Your plan</p>
                    <p class="text-3xl font-semibold">{{ $plan->name }}</p>
                </a>
            @endif
        </div>

        @if (($listingCounts['live'] ?? 0) === 0 && ($listingCounts['pending_review'] ?? 0) === 0)
            <p class="mt-8"><a href="{{ route('account.listings.create') }}" class="inline-flex min-h-11 items-center rounded bg-[var(--colour-button)] px-5 font-medium text-white">Add your first listing</a></p>
        @endif
    @endif

    @can('access-admin')
        <p class="mt-8"><a class="inline-flex min-h-11 items-center underline" href="{{ route('admin.dashboard') }}">Go to the admin area</a></p>
    @endcan
@endsection
