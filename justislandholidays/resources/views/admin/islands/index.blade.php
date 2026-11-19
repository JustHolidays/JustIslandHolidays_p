@extends('layouts.admin', ['title' => $single ? $single->translate('name', 'en') : 'Islands and resorts'])

@section('content')
    <h1 class="text-2xl font-semibold">{{ $single ? $single->translate('name', 'en') : 'Islands and resorts' }}</h1>
    <p class="mt-1">Each island and resort page needs original introduction text, ideally 400 words or more, written in-house.</p>
    @can('manage-islands')
        <p class="mt-2"><a href="{{ route('admin.locations.create', $single ? ['island' => $single->id] : []) }}" class="inline-flex min-h-11 items-center underline">Add a location</a></p>
    @endcan

    <ul class="mt-6 space-y-4">
        @foreach ($islands as $island)
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">
                        <a href="{{ route('admin.islands.edit', $island) }}" class="underline">{{ $island->translate('name', 'en') }}</a>
                        <span class="text-sm font-normal text-slate-600">/{{ $island->slug }} · {{ $island->live_count }} live · {{ $island->is_active ? 'On the site' : 'Switched off' }}</span>
                    </h2>
                    @can('manage-islands')
                        <a href="{{ route('admin.locations.create', ['island' => $island->id]) }}" class="inline-flex min-h-11 items-center rounded border border-slate-300 px-3">Add a resort or town</a>
                    @endcan
                </div>
                <ul class="mt-3 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($island->areas as $area)
                        <li><a href="{{ route('admin.areas.edit', $area) }}" class="inline-flex min-h-11 items-center underline">{{ $area->translate('name', 'en') }}</a>
                            <span class="text-sm text-slate-600">{{ $area->live_count }} live @if (empty($area->intro['en'])) · no text yet @endif</span></li>
                    @empty
                        <li class="text-slate-600">No resorts or towns yet.</li>
                    @endforelse
                </ul>
            </li>
        @endforeach
    </ul>
@endsection
