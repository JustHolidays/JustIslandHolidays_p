@extends('layouts.admin', ['title' => 'Add a property'])

@php
    $input = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
@endphp

@section('content')
    <p><a href="{{ route('admin.listings.index') }}" class="inline-flex min-h-11 items-center underline">Back to Listing Search</a></p>
    <h1 class="text-2xl font-semibold">Add a property</h1>
    <p class="mt-1 max-w-3xl">Add a property for an owner or agent. It is saved as theirs, so they can edit it in their account. If you tick that you have checked the licence in the official register, it goes live straight away and they are emailed. Otherwise it waits for review.</p>

    <form method="POST" action="{{ route('admin.listings.store') }}" enctype="multipart/form-data" class="mt-6 max-w-4xl space-y-6">
        @csrf

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="who-heading">
            <h2 id="who-heading" class="text-lg font-semibold">Whose property</h2>
            <div class="mt-3 max-w-md">
                <x-field name="lister_email" label="Owner or agent's email" type="email" :value="$listerEmail" hint="They must already have an owner or agent account." required autocomplete="off" />
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="basics-heading">
            <h2 id="basics-heading" class="text-lg font-semibold">The property</h2>
            <div class="mt-3">
                <x-field name="title" label="Title" maxlength="160" required />
                <div class="mb-4">
                    <label for="summary" class="mb-1 block font-medium">Short description</label>
                    <textarea id="summary" name="summary" rows="4" maxlength="500" required class="block w-full rounded border border-slate-400 px-3 py-2" @error('summary') aria-invalid="true" @enderror>{{ old('summary') }}</textarea>
                    @error('summary')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="area_id" class="mb-1 block font-medium">Island and resort</label>
                    <select id="area_id" name="area_id" required class="{{ $input }}">
                        <option value="">Choose a resort or town</option>
                        @foreach ($islands as $island)
                            <optgroup label="{{ $island->translate('name', 'en') }}">
                                @foreach ($areas[$island->id] ?? [] as $a)
                                    <option value="{{ $a->id }}" @selected((int) old('area_id') === $a->id)>{{ $a->translate('name', 'en') }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('area_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="property_type" class="mb-1 block font-medium">Type</label>
                    <select id="property_type" name="property_type" required class="{{ $input }}">
                        @foreach ($types as $code => $type)
                            <option value="{{ $code }}" @selected(old('property_type', 'villa') === $code)>{{ $type->translate('name', 'en') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <x-field name="bedrooms" label="Bedrooms" type="number" min="0" max="30" value="2" required />
                <x-field name="bathrooms" label="Bathrooms" type="number" min="0" max="30" value="1" required />
                <x-field name="sleeps" label="Sleeps" type="number" min="1" max="40" value="4" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="price" label="From price per week" type="number" min="1" hint="Their lowest weekly price, shown as From Price." required />
                <div>
                    <label for="currency" class="mb-1 block font-medium">Currency</label>
                    <select id="currency" name="currency" class="{{ $input }}"><option value="EUR" @selected(old('currency', 'EUR') === 'EUR')>Euros (€)</option><option value="GBP" @selected(old('currency') === 'GBP')>Pounds (£)</option></select>
                </div>
                <x-field name="distance_to_beach_m" label="Distance to the beach in metres (optional)" type="number" min="0" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="pets" class="mb-1 block font-medium">Pets</label>
                    <select id="pets" name="pets" class="{{ $input }}">
                        @foreach (['not_stated' => 'Not stated (hidden on the page)', 'yes' => 'Allowed', 'no' => 'Not allowed'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('pets', 'not_stated') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="pool" class="mb-1 block font-medium">Pool</label>
                    <select id="pool" name="pool" required class="{{ $input }}">
                        @foreach (['private' => 'Private pool', 'shared' => 'Shared pool', 'none' => 'No pool'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('pool', 'none') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    @error('pool')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="where-heading">
            <h2 id="where-heading" class="text-lg font-semibold">Location and enquiries</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <x-field name="coordinates" label="Map coordinates" hint="Two numbers from a map app, for example 39.9112, 4.2650. Visitors only see an area about 300 m away." required />
                <x-field name="contact_email" label="Email for enquiries (optional)" type="email" hint="Leave empty to use the owner or agent's account email." />
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="photo-heading">
            <h2 id="photo-heading" class="text-lg font-semibold">Photo</h2>
            <div class="mt-3 mb-4">
                <label for="photo" class="mb-1 block font-medium">Photo</label>
                <p id="photo-hint" class="mb-1 text-sm text-slate-600">JPG, PNG or WebP, at least 800 × 500 pixels, up to 10 MB.</p>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="photo-hint" class="block min-h-11">
                @error('photo')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <x-field name="image_alt" label="Photo description (alt text)" maxlength="160" hint="What the photo shows, for example: Villa with a pool and sea views." required />
            <div class="flex min-h-11 items-center gap-2">
                <input id="photo_permission" type="checkbox" name="photo_permission" value="1" class="h-5 w-5" @checked(old('photo_permission')) required>
                <label for="photo_permission">The owner or agent owns this photo or has permission to use it</label>
            </div>
            @error('photo_permission')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="licence-heading">
            <h2 id="licence-heading" class="text-lg font-semibold">Tourist licence</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <x-field name="registration_number" label="Balearic tourist licence number" hint="For example ETV/1234." />
                <x-field name="national_registry_no" label="National registry number (if they have one)" />
            </div>
            <div class="flex min-h-11 items-start gap-2">
                <input id="licence_checked" type="checkbox" name="licence_checked" value="1" class="mt-1 h-5 w-5" @checked(old('licence_checked'))>
                <label for="licence_checked">I have checked this number in the official register and it is valid for this property. <span class="text-slate-600">The property then goes live straight away. Leave it unticked to check it later on the Licence checks page.</span></label>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="link-heading">
            <h2 id="link-heading" class="text-lg font-semibold">Booking link (optional)</h2>
            <div class="mt-3 max-w-xl">
                <x-field name="deeplink_url" label="Check availability link" type="url" hint="Their own booking page, starting with https://. The owner or agent looks after it; we do not check it." />
            </div>
        </section>

        <x-button>Add property</x-button>
    </form>
@endsection
