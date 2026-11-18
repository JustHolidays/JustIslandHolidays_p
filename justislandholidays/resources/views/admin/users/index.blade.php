@extends('layouts.admin', ['title' => 'Owners and agents'])

@section('content')
    <h1 class="text-2xl font-semibold">Owners and agents</h1>
    @unless (auth()->user()->isSuperAdmin())
        <p class="mt-1">Read only: the owners and agents with listings on your islands.</p>
    @endunless

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter people">
        <div><label for="q" class="block text-sm font-medium">Name or email</label><input id="q" name="q" type="search" value="{{ request('q') }}" class="min-h-11 rounded border border-slate-400 px-2"></div>
        <div><label for="role" class="block text-sm font-medium">Role</label><select id="role" name="role" class="min-h-11 rounded border border-slate-400 bg-white px-2"><option value="">Both</option><option value="owner" @selected(request('role') === 'owner')>Owners</option><option value="agent" @selected(request('role') === 'agent')>Agents</option></select></div>
        <div><label for="state" class="block text-sm font-medium">Account</label><select id="state" name="state" class="min-h-11 rounded border border-slate-400 bg-white px-2"><option value="">Any</option><option value="deactivated" @selected(request('state') === 'deactivated')>Deactivated</option></select></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Filter</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Owners and agents</caption>
            <thead class="border-b border-slate-300"><tr>
                <x-admin.sort key="name" label="Name" /><th scope="col" class="p-2">Email</th><th scope="col" class="p-2">Role</th><th scope="col" class="p-2">Agency</th>
                <x-admin.sort key="listings" label="Listings" /><th scope="col" class="p-2">Plan</th><x-admin.sort key="login" label="Last sign-in" /><th scope="col" class="p-2">Account</th>
            </tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="border-b border-slate-200">
                        <td class="p-2"><a href="{{ route('admin.users.show', $u) }}" class="font-medium underline">{{ $u->name }}</a></td>
                        <td class="p-2">{{ $u->email }}</td>
                        <td class="p-2">{{ $u->role->label() }}</td>
                        <td class="p-2">{{ $u->agency?->name ?? '–' }}</td>
                        <td class="p-2">{{ $u->listings_count }}</td>
                        <td class="p-2">{{ $planNames[$u->id] }}</td>
                        <td class="p-2">{{ $u->last_login_at?->format('j M Y') ?? 'Never' }}</td>
                        <td class="p-2">{{ $u->isDeactivated() ? 'Deactivated' : 'Active' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4">Nobody matches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
