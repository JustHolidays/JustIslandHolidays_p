@extends('layouts.admin', ['title' => 'Audit log'])

@php
    $f = 'min-h-11 rounded border border-slate-400 bg-white px-2';
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">Audit log</h1>
    <p class="mt-1">Every change made by an admin or by the system. Read only.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter the audit log">
        <div><label for="actor" class="block text-sm font-medium">Who</label><select id="actor" name="actor" class="{{ $f }}"><option value="">Anyone</option><option value="system" @selected(request('actor') === 'system')>System</option>@foreach ($actors as $a)<option value="{{ $a->id }}" @selected((int) request('actor') === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
        <div><label for="action" class="block text-sm font-medium">Action contains</label><input id="action" name="action" value="{{ request('action') }}" class="{{ $f }}" placeholder="for example reject"></div>
        <div><label for="entity" class="block text-sm font-medium">Record type</label><select id="entity" name="entity" class="{{ $f }}"><option value="">Any</option>@foreach ($entities as $e)<option value="{{ $e }}" @selected(request('entity') === $e)>{{ $e }}</option>@endforeach</select></div>
        <div><label for="record" class="block text-sm font-medium">Record id</label><input id="record" name="record" value="{{ request('record') }}" class="{{ $f }} w-28"></div>
        <div><label for="from" class="block text-sm font-medium">From</label><input id="from" name="from" type="date" value="{{ request('from') }}" class="{{ $f }}"></div>
        <div><label for="to" class="block text-sm font-medium">To</label><input id="to" name="to" type="date" value="{{ request('to') }}" class="{{ $f }}"></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Filter</button>
        <a href="{{ route('admin.audit.export', request()->query()) }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-4">Export CSV</a>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Audit entries, newest first</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Time</th><th scope="col" class="p-2">Who</th><th scope="col" class="p-2">Action</th><th scope="col" class="p-2">Record</th><th scope="col" class="p-2">Detail</th></tr></thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr class="border-b border-slate-100 align-top">
                        <td class="p-2 whitespace-nowrap">{{ $e->created_at->format('j M Y H:i:s') }}</td>
                        <td class="p-2">{{ $e->actor?->name ?? 'System' }}</td>
                        <td class="p-2 font-mono">{{ $e->action }}</td>
                        <td class="p-2">{{ $e->entity }} {{ $e->entity_id }}</td>
                        <td class="p-2"><code class="block max-w-xl overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ $e->detail ? json_encode($e->detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4">No entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
