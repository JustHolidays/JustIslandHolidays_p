@extends('layouts.admin', ['title' => 'Affiliates'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Affiliates</h1>
        <a href="{{ route('admin.affiliates.create') }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add an affiliate</a>
    </div>

    <form method="GET" class="mt-4 flex items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Search affiliates">
        <div><label for="q" class="block text-sm font-medium">Name or email</label><input id="q" name="q" type="search" value="{{ request('q') }}" class="min-h-11 rounded border border-slate-400 px-2"></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Search</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Affiliates</caption>
            <thead class="border-b border-slate-300"><tr>
                <x-admin.sort key="name" label="Name" default="name" /><th scope="col" class="p-2">Email</th><x-admin.sort key="listings" label="Listings" /><th scope="col" class="p-2">Live</th>
                <x-admin.sort key="clicks" label="Clicks, 30 days" /><th scope="col" class="p-2">Commission pending</th><th scope="col" class="p-2">Account</th>
            </tr></thead>
            <tbody>
                @forelse ($affiliates as $a)
                    <tr class="border-b border-slate-200">
                        <td class="p-2"><a href="{{ route('admin.affiliates.show', $a) }}" class="font-medium underline">{{ $a->name }}</a></td>
                        <td class="p-2">{{ $a->email }}</td>
                        <td class="p-2">{{ $a->listings_count }}</td>
                        <td class="p-2">{{ $a->live_count }}</td>
                        <td class="p-2">{{ (int) $a->clicks_30 }}</td>
                        <td class="p-2">
                            @forelse ($pending[$a->id] ?? [] as $row){{ $row->currency === 'GBP' ? '£' : '€' }}{{ number_format($row->total / 100, 2) }} @empty – @endforelse
                        </td>
                        <td class="p-2">{{ $a->isDeactivated() ? 'Deactivated' : 'Active' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4">No affiliates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $affiliates->links() }}</div>
@endsection
