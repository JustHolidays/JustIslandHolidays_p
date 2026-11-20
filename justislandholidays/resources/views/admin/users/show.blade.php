@extends('layouts.admin', ['title' => $user->name])

@php
    $input = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
@endphp

@section('content')
    @if ($user->role === \App\Enums\Role::Agent)
        <p class="flex flex-wrap gap-4"><a href="{{ route('admin.agents.index') }}" class="inline-flex min-h-11 items-center underline">Back to agents</a> <a href="{{ route('admin.agents.show', $user) }}" class="inline-flex min-h-11 items-center underline">See their properties</a></p>
    @else
        <p><a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center underline">Back to owners</a></p>
    @endif
    <h1 class="text-2xl font-semibold">{{ $user->name }}</h1>
    <p class="mt-1">{{ $user->role->label() }} · {{ $user->email }} · {{ $plan->name }} plan · {{ $user->isDeactivated() ? 'Deactivated '.$user->deactivated_at->format('j M Y') : 'Active' }} · last sign-in {{ $user->last_login_at?->format('j M Y') ?? 'never' }}</p>
    @unless ($user->isDeactivated())
        <p class="mt-2 flex flex-wrap gap-2"><a href="{{ route('admin.listings.create', ['lister' => $user->email]) }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add a property for them</a>
            @if (! $user->last_login_at && in_array($user->role, [\App\Enums\Role::Owner, \App\Enums\Role::Agent], true))
                @can('manage-owners')
                    <form method="POST" action="{{ route('admin.users.invite', $user) }}" data-invite-form>@csrf<button type="submit" class="min-h-11 rounded border border-slate-900 bg-white px-4 font-medium">Send invitation to set a password</button></form>
                @endcan
            @endif
        </p>
    @endunless

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="listings-heading">
        <h2 id="listings-heading" class="text-lg font-semibold">Listings</h2>
        <ul class="mt-2 space-y-1">
            @forelse ($listings as $l)
                <li><a href="{{ route('admin.listings.show', $l) }}" class="inline-flex min-h-11 items-center underline">{{ $l->title('en') }}</a> – {{ $l->island->translate('name', 'en') }}, {{ $l->status->label() }}</li>
            @empty
                <li>No listings on your islands.</li>
            @endforelse
        </ul>
    </section>

    @can('update', $user)
        <section class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="edit-heading">
            <h2 id="edit-heading" class="text-lg font-semibold">Details</h2>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-3">
                @csrf @method('PUT')
                <x-field name="name" label="Name" :value="$user->name" required />
                <x-field name="email" label="Email" type="email" :value="$user->email" required />
                <x-field name="phone" label="Phone" :value="$user->phone" />
                <div class="mb-4"><label for="role" class="mb-1 block font-medium">Role</label>
                    <select id="role" name="role" class="{{ $input }}"><option value="owner" @selected($user->role->value === 'owner')>Owner</option><option value="agent" @selected($user->role->value === 'agent')>Agent</option></select></div>
                <div class="mb-4"><label for="agency_id" class="mb-1 block font-medium">Agency (agents only)</label>
                    <select id="agency_id" name="agency_id" class="{{ $input }}"><option value="">None</option>@foreach ($agencies as $a)<option value="{{ $a->id }}" @selected($user->agency_id === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                <x-button>Save</x-button>
            </form>
        </section>

        <section class="mt-6 max-w-2xl" aria-label="Account status">
            @if ($user->isDeactivated())
                <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">@csrf<button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Reactivate account</button></form>
            @else
                <x-admin.reason-action :action="route('admin.users.deactivate', $user)" label="Deactivate account" confirm="Deactivate this account? They are signed out and all their listings are paused." />
            @endif
        </section>
    @endcan

    @can('recommendSuspension', $user)
        <section class="mt-6 max-w-2xl" aria-label="Recommend suspension">
            @if ($openRecommendation)
                <p class="rounded bg-amber-50 p-3">A suspension has been recommended. A super admin will decide.</p>
            @else
                <x-admin.reason-action :action="route('admin.users.recommend', $user)" label="Recommend suspension" hint="A super admin reads this and decides." :danger="false" />
            @endif
        </section>
    @endcan

    @if ($openRecommendation && auth()->user()->isSuperAdmin())
        <section class="mt-6 max-w-2xl rounded-lg border-2 border-amber-400 bg-white p-5 shadow-sm" aria-labelledby="rec-heading">
            <h2 id="rec-heading" class="text-lg font-semibold">Suspension recommended</h2>
            <p class="mt-2">“{{ $openRecommendation->reason }}”</p>
            <form method="POST" action="{{ route('admin.recommendations.decide', $openRecommendation) }}" class="mt-3 space-y-2">
                @csrf
                <label for="decision_note" class="block font-medium">Note (optional)</label>
                <textarea id="decision_note" name="decision_note" rows="2" maxlength="500" class="block w-full rounded border border-slate-400 px-3 py-2"></textarea>
                <div class="flex gap-2">
                    <button name="decision" value="accept" class="min-h-11 rounded bg-red-800 px-4 font-medium text-white" onclick="return confirm('Deactivate this account?')">Deactivate the account</button>
                    <button name="decision" value="decline" class="min-h-11 rounded border border-slate-400 px-4">Send back</button>
                </div>
            </form>
        </section>
    @endif
@endsection
