@extends('layouts.admin', ['title' => 'Property types'])

@section('content')
    <h1 class="text-2xl font-semibold">Property types</h1>
    <p class="mt-1 max-w-3xl">The kinds of property owners choose from, and visitors filter by. Switching a type off hides it from new listings and the search filters; listings that already use it keep it. A type with listings cannot be deleted. The code is fixed once added, because web addresses use it.</p>

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Property types</caption>
            <thead class="border-b border-slate-300">
                <tr><th scope="col" class="p-2">Order</th><th scope="col" class="p-2">Name</th><th scope="col" class="p-2">Spanish</th><th scope="col" class="p-2">German</th>
                    <th scope="col" class="p-2">Code</th><th scope="col" class="p-2 text-right">Listings</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr class="border-b border-slate-200">
                        <td class="p-2">{{ $type->sort_order }}</td>
                        <td class="p-2 font-medium">{{ $type->translate('name', 'en') }}</td>
                        <td class="p-2">{{ $type->name['es'] ?? '' }}</td>
                        <td class="p-2">{{ $type->name['de'] ?? '' }}</td>
                        <td class="p-2 font-mono">{{ $type->code }}</td>
                        <td class="p-2 text-right">{{ (int) ($counts[$type->code] ?? 0) }}</td>
                        <td class="p-2">{{ $type->is_active ? 'On' : 'Switched off' }}</td>
                        <td class="p-2"><a href="{{ route('admin.property-types.edit', $type) }}" class="inline-flex min-h-11 items-center underline">Edit<span class="sr-only"> {{ $type->translate('name', 'en') }}</span></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="mt-6 max-w-xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="add-heading">
        <h2 id="add-heading" class="text-lg font-semibold">Add a property type</h2>
        <form method="POST" action="{{ route('admin.property-types.store') }}" class="mt-3">
            @csrf
            <x-field name="new_name_en" id="new-name" label="Name in English" hint="For example Country house." required />
            <x-field name="new_code" id="new-code" label="Code" hint="Lower case letters, numbers and underscores, for example country_house. It cannot be changed later." required />
            <x-button>Add property type</x-button>
        </form>
    </section>
@endsection
