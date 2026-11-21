@php
    $isAgents = $role === \App\Enums\Role::Agent;
    $heading = $isAgents ? 'Agents' : 'Owners';
@endphp

@extends('layouts.admin', ['title' => $heading])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">{{ $heading }}</h1>
        <div class="flex flex-wrap gap-2">
            @can('manage-user-csv')
                <a href="{{ route('admin.users.csv') }}" class="inline-flex min-h-11 items-center rounded border border-slate-900 px-4 font-medium">Import and export</a>
            @endcan
            @can('manage-owners')
                <a href="{{ route('admin.users.create', $isAgents ? ['as' => 'agent'] : []) }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">{{ $isAgents ? 'Add an agent' : 'Add an owner' }}</a>
            @endcan
        </div>
    </div>
    @if (auth()->user()->isSuperAdmin())
        <p class="mt-1">To move someone between Owners and Agents, change their role on their page.</p>
    @else
        <p class="mt-1">Read only: the {{ strtolower($heading) }} with listings on your islands.</p>
    @endif

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter people">
        <div><label for="q" class="block text-sm font-medium">Name or email</label><input id="q" name="q" type="search" value="{{ request('q') }}" class="min-h-11 rounded border border-slate-400 px-2"></div>
        <div><label for="state" class="block text-sm font-medium">Account</label><select id="state" name="state" class="min-h-11 rounded border border-slate-400 bg-white px-2"><option value="">Any</option><option value="deactivated" @selected(request('state') === 'deactivated')>Deactivated</option></select></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Filter</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ $heading }}</caption>
            <thead class="border-b border-slate-300"><tr>
                <x-admin.sort key="name" label="Name" /><th scope="col" class="p-2">Email</th>@if ($isAgents)<th scope="col" class="p-2">Agency</th>@endif
                <x-admin.sort key="listings" label="Listings" /><th scope="col" class="p-2">Plan</th><x-admin.sort key="login" label="Last sign-in" /><th scope="col" class="p-2">Account</th>
            </tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="border-b border-slate-200">
                        <td class="p-2"><a href="{{ $isAgents ? route('admin.agents.show', $u) : route('admin.users.show', $u) }}" class="font-medium underline">{{ $u->name }}</a></td>
                        <td class="p-2">{{ $u->email }}</td>
                        @if ($isAgents)<td class="p-2">{{ $u->agency?->name ?? '–' }}</td>@endif
                        <td class="p-2">{{ $u->listings_count }}</td>
                        <td class="p-2">{{ $planNames[$u->id] }}</td>
                        <td class="p-2">{{ $u->last_login_at?->format('j M Y') ?? 'Never' }}</td>
                        <td class="p-2">{{ $u->isDeactivated() ? 'Deactivated' : 'Active' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isAgents ? 7 : 6 }}" class="p-4">Nobody matches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
