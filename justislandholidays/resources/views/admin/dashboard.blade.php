@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    <h1 class="text-2xl font-semibold">Dashboard</h1>

    <ul class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <li>
            <a href="{{ route('admin.listings.index', ['status' => 'pending_review']) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-slate-400">
                <p class="text-sm text-slate-600">Listings waiting for review</p>
                <p class="text-3xl font-semibold">{{ $pending }}</p>
                @if ($overdue > 0)
                    <p class="mt-1 font-medium text-red-800">{{ $overdue }} waiting more than {{ $target }} hours</p>
                @endif
            </a>
        </li>
        <li>
            <a href="{{ route('admin.licences.index') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-slate-400">
                <p class="text-sm text-slate-600">Licences to check</p>
                <p class="text-3xl font-semibold">{{ $licences }}</p>
            </a>
        </li>
        <li>
            <a href="{{ route('admin.deeplinks.index', ['status' => 'broken']) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-slate-400">
                <p class="text-sm text-slate-600">Broken booking links on live listings</p>
                <p @class(['text-3xl font-semibold', 'text-red-800' => $broken > 0])>{{ $broken }}</p>
            </a>
        </li>
        <li>
            <a href="{{ route('admin.clicks.index', ['from' => now()->subDays(7)->toDateString()]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-slate-400">
                <p class="text-sm text-slate-600">Check availability clicks, last 7 days</p>
                <p class="text-3xl font-semibold">{{ $clicks7 }}</p>
                <p class="mt-1 text-sm">{{ $clicksPrev7 }} the 7 days before</p>
            </a>
        </li>
        @if ($recommendations !== null)
            <li>
                <a href="{{ route('admin.listings.index', ['tab' => 'recommended']) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-slate-400">
                    <p class="text-sm text-slate-600">Recommendations to decide</p>
                    <p class="text-3xl font-semibold">{{ $recommendations }}</p>
                </a>
            </li>
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-600">Commission pending</p>
                @forelse ($commission as $currency => $total)
                    <p class="text-2xl font-semibold">{{ $currency === 'GBP' ? '£' : '€' }}{{ number_format($total / 100, 2) }}</p>
                @empty
                    <p class="text-2xl font-semibold">None</p>
                @endforelse
            </li>
        @endif
    </ul>

    @if ($audit->isNotEmpty())
        <section class="mt-8" aria-labelledby="audit-heading">
            <h2 id="audit-heading" class="text-lg font-semibold">Latest changes</h2>
            <ul class="mt-2 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
                @foreach ($audit as $entry)
                    <li class="p-3 text-sm">
                        <span class="text-slate-600">{{ $entry->created_at->format('j M H:i') }}</span>
                        · {{ $entry->actor?->name ?? 'System' }} · {{ $entry->action }} · {{ $entry->entity }} {{ $entry->entity_id }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-2"><a href="{{ route('admin.audit.index') }}" class="inline-flex min-h-11 items-center underline">Open the audit log</a></p>
        </section>
    @endif
@endsection
