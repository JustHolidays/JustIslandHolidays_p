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

    @if ($user->role === \App\Enums\Role::Visitor)
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('account.favourites') }}" class="block rounded-lg border border-slate-200 bg-white p-5 hover:border-[var(--colour-brand)]">
                <p class="text-sm text-slate-600">My favourites</p>
                <p class="text-3xl font-semibold">{{ count(\App\Listings\Favourites::idsFor($user)) }}</p>
                <p class="text-sm">Homes you saved with the heart</p>
            </a>
        </div>
        <p class="mt-6"><a href="{{ route('search', 'en') }}" class="inline-flex min-h-11 items-center rounded bg-[var(--colour-button)] px-5 font-medium text-white">Find a holiday home</a></p>
    @endif

    @can('access-admin')
        <p class="mt-8"><a class="inline-flex min-h-11 items-center underline" href="{{ route('admin.dashboard') }}">Go to the admin area</a></p>
    @endcan

    {{-- Close my account (20 Nov 2026): a request our team actions. --}}
    @if (\App\Admin\AccountClosure::canRequest($user))
        <section id="close-account" class="mt-12 max-w-2xl scroll-mt-4 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="close-heading">
            <h2 id="close-heading" class="text-lg font-semibold">Close my account</h2>
            @if ($user->closure_requested_at)
                <p class="mt-1">You asked us to close your account on {{ $user->closure_requested_at->format('j F Y') }}. We will close it and email you, usually within a few days.</p>
                <form method="POST" action="{{ route('account.close.destroy') }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button type="submit" class="min-h-11 rounded border border-slate-400 px-4">Withdraw my request</button>
                </form>
            @else
                <p class="mt-1 text-sm text-slate-700">
                    @if ($user->role === \App\Enums\Role::Visitor)
                        Your favourites will be deleted.
                    @else
                        Your listings will be taken down and any paid plan will not renew.
                    @endif
                    You will no longer be able to sign in, and your personal details are removed within 30 days of closing. You can withdraw the request until we have closed the account.
                </p>
                <form method="POST" action="{{ route('account.close.store') }}" class="mt-3" onsubmit="return confirm('Send a request to close your account?')">
                    @csrf
                    <label for="closure_reason" class="mb-1 block font-medium">Anything you would like to tell us? (optional)</label>
                    <textarea id="closure_reason" name="closure_reason" rows="3" maxlength="500" class="block w-full rounded border border-slate-400 px-3 py-2">{{ old('closure_reason') }}</textarea>
                    @error('closure_reason')<p class="mt-1 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
                    <button type="submit" class="mt-3 min-h-11 rounded border border-red-800 px-4 font-medium text-red-800 hover:bg-red-50">Close my account</button>
                </form>
            @endif
        </section>
    @endif
@endsection
