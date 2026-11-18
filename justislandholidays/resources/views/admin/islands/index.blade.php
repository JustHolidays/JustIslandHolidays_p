@extends('layouts.admin', ['title' => 'Islands and resorts'])

@section('content')
    <h1 class="text-2xl font-semibold">Islands and resorts</h1>
    <p class="mt-1">Each island and resort page needs original introduction text, ideally 400 words or more, written in-house.</p>

    <ul class="mt-6 space-y-4">
        @foreach ($islands as $island)
            <li class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">
                        <a href="{{ route('admin.islands.edit', $island) }}" class="underline">{{ $island->translate('name', 'en') }}</a>
                        <span class="text-sm font-normal text-slate-600">/{{ $island->slug }} · {{ $island->live_count }} live · {{ $island->is_active ? 'On the site' : 'Switched off' }}</span>
                    </h2>
                    @can('manage-islands')
                        <details class="rounded border border-slate-300">
                            <summary class="flex min-h-11 cursor-pointer items-center px-3">Add a resort or town</summary>
                            <form method="POST" action="{{ route('admin.areas.store', $island) }}" class="space-y-2 p-3">
                                @csrf
                                <x-field name="name_en" :id="'area-name-'.$island->id" label="Name" required />
                                <x-field name="slug" :id="'area-slug-'.$island->id" label="Web address part" hint="Lower case letters, numbers and hyphens, for example cala-galdana." required />
                                <label for="kind-{{ $island->id }}" class="block font-medium">Kind</label>
                                <select id="kind-{{ $island->id }}" name="kind" class="min-h-11 rounded border border-slate-400 bg-white px-2"><option value="beach_resort">Beach resort</option><option value="coastal_resort">Coastal resort</option><option value="town">Town</option></select>
                                <x-button>Add</x-button>
                            </form>
                        </details>
                    @endcan
                </div>
                <ul class="mt-3 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($island->areas as $area)
                        <li><a href="{{ route('admin.areas.edit', $area) }}" class="inline-flex min-h-11 items-center underline">{{ $area->translate('name', 'en') }}</a>
                            <span class="text-sm text-slate-600">{{ $area->live_count }} live @if (empty($area->intro['en'])) · no text yet @endif</span></li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    @can('manage-islands')
        <details class="mt-6 max-w-xl rounded-lg bg-white shadow-sm">
            <summary class="flex min-h-11 cursor-pointer items-center px-5 font-semibold">Add an island</summary>
            <form method="POST" action="{{ route('admin.islands.store') }}" class="space-y-2 p-5">
                @csrf
                <x-field name="name_en" id="island-name" label="Name" required />
                <x-field name="slug" id="island-slug" label="Web address part" hint="For example ibiza." required />
                <x-button>Add island (switched off)</x-button>
            </form>
        </details>
    @endcan
@endsection
