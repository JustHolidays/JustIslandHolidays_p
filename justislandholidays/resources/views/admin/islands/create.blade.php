@extends('layouts.admin', ['title' => 'Add a location'])

@section('content')
    <h1 class="text-2xl font-semibold">Add a location</h1>
    <p class="mt-1 max-w-3xl">Add a resort or town to an island, or a whole new island. After adding it you can write its introduction and set its map centre.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="resort-heading">
            <h2 id="resort-heading" class="text-lg font-semibold">A resort or town</h2>
            <form method="POST" action="{{ route('admin.locations.area') }}" class="mt-3 space-y-2">
                @csrf
                <label for="area-island" class="block font-medium">Island</label>
                <select id="area-island" name="island" required class="mb-4 block min-h-11 w-full rounded border border-slate-400 bg-white px-2">
                    <option value="">Choose an island</option>
                    @foreach ($islands as $i)
                        <option value="{{ $i->id }}" @selected((int) old('island', $chosen?->id) === $i->id)>{{ $i->translate('name', 'en') }}{{ $i->is_active ? '' : ' (switched off)' }}</option>
                    @endforeach
                </select>
                <x-field name="name_en" id="area-name" label="Name" required />
                <x-field name="slug" id="area-slug" label="Web address part" hint="Lower case letters, numbers and hyphens, for example cala-galdana." required />
                <label for="area-kind" class="block font-medium">Kind</label>
                <select id="area-kind" name="kind" class="mb-4 block min-h-11 rounded border border-slate-400 bg-white px-2">
                    <option value="beach_resort" @selected(old('kind') === 'beach_resort')>Beach resort</option>
                    <option value="coastal_resort" @selected(old('kind') === 'coastal_resort')>Coastal resort</option>
                    <option value="town" @selected(old('kind') === 'town')>Town</option>
                </select>
                <x-button>Add resort or town</x-button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="island-heading">
            <h2 id="island-heading" class="text-lg font-semibold">A new island</h2>
            <p class="mt-1 text-sm">New islands start switched off, so visitors do not see them until you are ready.</p>
            <form method="POST" action="{{ route('admin.islands.store') }}" class="mt-3 space-y-2">
                @csrf
                <x-field name="island_name" id="island-name" label="Name" required />
                <x-field name="island_slug" id="island-slug" label="Web address part" hint="For example ibiza." required />
                <x-button>Add island (switched off)</x-button>
            </form>
        </section>
    </div>
@endsection
