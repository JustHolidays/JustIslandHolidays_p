@extends('layouts.admin', ['title' => 'Clicks: each event'])

@section('content')
    <p><a href="{{ route('admin.clicks.index', request()->query()) }}" class="inline-flex min-h-11 items-center underline">Back to click reports</a></p>
    <h1 class="text-2xl font-semibold">Each click</h1>
    <p class="mt-1">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}. Visitors' addresses are never stored or shown.</p>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Click events, newest first</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Time</th><th scope="col" class="p-2">Listing</th><th scope="col" class="p-2">From page</th><th scope="col" class="p-2">Device</th><th scope="col" class="p-2">Bot</th><th scope="col" class="p-2">Unique</th></tr></thead>
            <tbody>
                @forelse ($clicks as $c)
                    <tr class="border-b border-slate-100"><td class="p-2 whitespace-nowrap">{{ $c->clicked_at->format('j M Y H:i') }}</td><td class="p-2">{{ $c->listing?->title('en') }}</td><td class="p-2">{{ $c->source_page }}</td><td class="p-2">{{ $c->device }}</td><td class="p-2">{{ $c->is_bot ? 'Yes' : 'No' }}</td><td class="p-2">{{ $c->is_unique ? 'Yes' : 'No' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="p-4">No clicks.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $clicks->links() }}</div>
@endsection
