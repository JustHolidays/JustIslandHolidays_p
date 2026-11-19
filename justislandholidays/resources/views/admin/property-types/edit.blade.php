@extends('layouts.admin', ['title' => 'Edit '.$type->translate('name', 'en')])

@section('content')
    <p><a href="{{ route('admin.property-types.index') }}" class="inline-flex min-h-11 items-center underline">Back to property types</a></p>
    <h1 class="text-2xl font-semibold">{{ $type->translate('name', 'en') }}</h1>
    <p class="mt-1">Code <span class="font-mono">{{ $type->code }}</span> (fixed) · {{ trans_choice(':count listing uses it|:count listings use it', $used) }}</p>

    <form method="POST" action="{{ route('admin.property-types.update', $type) }}" class="mt-6 max-w-3xl rounded-lg bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($langs as $code => $label)
                <x-field :name="'name_'.$code" :label="'Name in '.$label" :value="$type->name[$code] ?? null" :required="$code === 'en'" />
            @endforeach
        </div>
        <p class="mb-4 text-sm text-slate-600">Spanish and German show once those languages are switched on. Leave one empty to use the English name.</p>
        <div class="w-28"><x-field name="sort_order" label="Order" type="number" min="0" max="1000" :value="$type->sort_order" hint="Lower comes first." required /></div>
        <div class="mb-4 flex min-h-11 items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="h-5 w-5" @checked(old('is_active', $type->is_active))>
            <label for="is_active">Switched on (offered for new listings and in the search filters)</label>
        </div>
        <x-button>Save</x-button>
    </form>

    @if ($used === 0)
        <form method="POST" action="{{ route('admin.property-types.destroy', $type) }}" class="mt-6" onsubmit="return confirm('Delete this property type for good?')">
            @csrf @method('DELETE')
            <button type="submit" class="min-h-11 rounded border border-red-800 px-4 text-red-800">Delete this property type</button>
        </form>
    @else
        <p class="mt-6 text-sm">Listings use this type, so it cannot be deleted. Switch it off instead.</p>
    @endif
@endsection
