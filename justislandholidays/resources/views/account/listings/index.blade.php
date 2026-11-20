@extends('layouts.account', ['title' => 'Your listings'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Your listings</h1>
        @if ($atLimit)
            {{-- Plan limit reached (20 Nov 2026): shown greyed out, not a link. --}}
            <span role="link" aria-disabled="true" aria-describedby="limit-note" class="inline-flex min-h-11 cursor-not-allowed items-center rounded bg-slate-300 px-5 font-medium text-slate-700" data-add-listing="disabled">Add a listing</span>
        @else
            <a href="{{ route('account.listings.create') }}" class="inline-flex min-h-11 items-center rounded bg-[var(--colour-button)] px-5 font-medium text-white" data-add-listing="enabled">Add a listing</a>
        @endif
    </div>

    @if ($plan)
        <p class="mt-2">You are on the <strong>{{ $plan->name }}</strong> plan
            ({{ $plan->max_listings === null ? 'unlimited listings' : trans_choice(':count listing|up to :count listings', $plan->max_listings) }}).
            <a href="{{ route('account.billing') }}" class="underline">Change plan</a></p>
    @endif
    @if ($atLimit)
        <p id="limit-note" role="status" class="mt-3 rounded border border-amber-300 bg-amber-50 p-3">
            You have {{ trans_choice(':count listing|:count listings', $held) }}, the most the {{ $plan->name }} allows, so you cannot add another.
            Archive one you no longer need, or <a href="{{ route('account.billing') }}" class="font-medium underline">choose a plan with more listings</a>.
        </p>
    @endif
    @error('limit')
        @unless ($atLimit)<p class="mt-3 text-sm font-medium text-red-800">{{ $message }}</p>@endunless
    @enderror

    @if ($listings->isEmpty())
        <p class="mt-8">You have no listings yet. Add your first home to get started.</p>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($listings as $listing)
                @php($status = $listing->status)
                <li class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ $listing->title('en') }}</h2>
                            <p class="text-sm text-slate-700">{{ $listing->area->translate('name') }}, {{ $listing->island->translate('name') }}</p>
                            <p class="mt-2">
                                <span @class([
                                    'inline-block rounded px-2 py-1 text-sm font-medium',
                                    'bg-green-100 text-green-900' => $status->value === 'live',
                                    'bg-amber-100 text-amber-900' => in_array($status->value, ['pending_review', 'paused']),
                                    'bg-red-100 text-red-900' => $status->value === 'rejected',
                                    'bg-slate-100 text-slate-800' => in_array($status->value, ['draft', 'archived']),
                                ])>{{ $status->label() }}</span>
                                @if ($listing->licence && ! $listing->isAffiliateListing())
                                    <span class="ml-2 text-sm">Licence: {{ ['not_provided' => 'not added', 'pending' => 'waiting for check', 'verified' => 'verified', 'rejected' => 'not accepted'][$listing->licence->status] }}</span>
                                @endif
                            </p>
                            @if ($status->value === 'rejected' && $listing->rejection_reason)
                                <p class="mt-2 text-sm"><strong>Reason:</strong> {{ $listing->rejection_reason }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($status->value === 'live')
                                <a href="{{ $listing->publicUrl('en') }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-4">View</a>
                            @endif
                            @unless (in_array($status->value, ['pending_review', 'archived']))
                                <a href="{{ route('account.listings.edit', [$listing, 'basics']) }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-4">Edit</a>
                            @endunless
                            @foreach (['draft' => ['submit' => 'Send for review'], 'rejected' => ['reopen' => 'Edit and send again'], 'live' => ['pause' => 'Pause', 'archive' => 'Archive'], 'paused' => ['resume' => 'Make live again', 'archive' => 'Archive']][$status->value] ?? [] as $action => $label)
                                <form method="POST" action="{{ route('account.listings.status', [$listing, $action]) }}"
                                      @if ($action === 'archive') onsubmit="return confirm('Archive this listing? It will be removed from the site and cannot be edited again.')" @endif>
                                    @csrf
                                    <button type="submit" class="min-h-11 rounded border border-slate-400 px-4">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
