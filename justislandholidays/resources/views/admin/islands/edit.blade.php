@extends('layouts.admin', ['title' => 'Edit '.$place->translate('name', 'en')])

@php
    $isSuper = auth()->user()->can('manage', $place);
    $action = $kind === 'island' ? route('admin.islands.update', $place) : route('admin.areas.update', $place);
@endphp

@section('content')
    <p><a href="{{ route('admin.islands.index') }}" class="inline-flex min-h-11 items-center underline">Back to islands and resorts</a></p>
    <h1 class="text-2xl font-semibold">{{ $place->translate('name', 'en') }}@if ($kind === 'area') <span class="text-base font-normal">on {{ $place->island->translate('name', 'en') }}</span>@endif</h1>

    <form method="POST" action="{{ $action }}" class="mt-6 max-w-3xl space-y-6">
        @csrf @method('PUT')

        @if ($isSuper)
            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="basics-heading">
                <h2 id="basics-heading" class="text-lg font-semibold">Name and settings</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    @foreach ($langs as $code => $label)
                        <x-field :name="'name_'.$code" :label="'Name in '.$label" :value="$place->name[$code] ?? null" :required="$code === 'en'" />
                    @endforeach
                </div>
                <x-field name="slug" label="Web address part" :value="$place->slug" :hint="$slugLocked ? 'Locked: there are live listings here, so the web address cannot change.' : 'Lower case letters, numbers and hyphens.'" :readonly="$slugLocked" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="centre_lat" label="Map centre, latitude" :value="$place->centre_lat" inputmode="decimal" />
                    <x-field name="centre_lng" label="Map centre, longitude" :value="$place->centre_lng" inputmode="decimal" />
                    <x-field name="sort_order" label="Order" type="number" min="0" :value="$place->sort_order" />
                </div>
                @if ($kind === 'island')
                    <div class="flex min-h-11 items-center gap-2"><input type="hidden" name="is_active" value="0"><input id="is_active" type="checkbox" name="is_active" value="1" class="h-5 w-5" @checked($place->is_active)><label for="is_active">Shown on the site</label></div>
                @else
                    <label for="kind" class="mb-1 block font-medium">Kind</label>
                    <select id="kind" name="kind" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        @foreach (['beach_resort' => 'Beach resort', 'coastal_resort' => 'Coastal resort', 'town' => 'Town'] as $v => $l)<option value="{{ $v }}" @selected($place->kind === $v)>{{ $l }}</option>@endforeach
                    </select>
                @endif
            </section>
        @endif

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="intro-heading">
            <h2 id="intro-heading" class="text-lg font-semibold">Introduction text</h2>
            <p class="mt-1 text-sm">Shown at the top of the page. You can use Markdown: **bold**, ## headings, - lists. Spanish and German appear once those languages are switched on; drafts need a native speaker to check them.</p>
            @foreach ($langs as $code => $label)
                <div class="mt-4">
                    <label for="intro_{{ $code }}" class="mb-1 block font-medium">{{ $label }}</label>
                    <textarea id="intro_{{ $code }}" name="intro_{{ $code }}" rows="{{ $code === 'en' ? 12 : 6 }}" class="block w-full rounded border border-slate-400 px-3 py-2">{{ old('intro_'.$code, $place->intro[$code] ?? '') }}</textarea>
                    <p class="mt-1 text-sm text-slate-600">{{ str_word_count(strip_tags($place->intro[$code] ?? '')) }} words saved</p>
                </div>
            @endforeach
        </section>

        <x-button>Save</x-button>
    </form>

    {{-- Islands only: resort pages have no header image. A separate form, so an upload is saved straight away. --}}
    @if ($kind === 'island')
        @php
            $headerAction = route('admin.islands.header', $place);
            $headerUrl = \App\Support\HeaderImage::url($place);
        @endphp
        <section class="mt-6 max-w-3xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="header-heading">
            <h2 id="header-heading" class="text-lg font-semibold">Page header image</h2>
            <p class="mt-1 text-sm">Shown full width at the top of the island page, below the menu, {{ \App\Support\HeaderImage::HEIGHT }} pixels tall (shorter on phones) and cropped to fit. A wide landscape photo works best, ideally 2400 × {{ \App\Support\HeaderImage::HEIGHT }} pixels or larger. JPG or WebP, at least {{ \App\Support\HeaderImage::MIN_WIDTH }} pixels wide and {{ \App\Support\HeaderImage::HEIGHT }} tall, up to 5 MB. Smaller copies for phones are made for you. Resort and town pages have no header image.</p>

            @if ($headerUrl)
                <img src="{{ $headerUrl }}" alt="{{ $place->header_image_alt }}" class="mt-3 aspect-[6/1] w-full rounded object-cover" width="2400" height="400">
            @else
                <img src="{{ \App\Support\HeaderImage::placeholderUrl() }}" alt="" class="mt-3 aspect-[6/1] w-full rounded object-cover" width="2400" height="400">
                <p class="mt-2 text-sm font-medium">The page shows this placeholder until you add a photo.</p>
            @endif

            <form method="POST" action="{{ $headerAction }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                @csrf
                <label for="header_image" class="block font-medium">{{ $headerUrl ? 'Replace the image (optional)' : 'Image' }}</label>
                <input id="header_image" name="header_image" type="file" accept="image/jpeg,image/webp" class="block min-h-11" @unless ($headerUrl) required @endunless
                       @error('header_image') aria-invalid="true" aria-errormessage="header_image-error" @enderror>
                @error('header_image')<p id="header_image-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                <x-field name="header_image_alt" label="Description (alt text)" :value="$place->header_image_alt" hint="What the photo shows, for people who cannot see it. For example: Turquoise water and white sand at Cala Macarella." required />
                <x-button>{{ $headerUrl ? 'Save header image' : 'Add header image' }}</x-button>
            </form>
            @if ($headerUrl)
                <form method="POST" action="{{ $headerAction }}" class="mt-2" onsubmit="return confirm('Remove the header image from this page?')">
                    @csrf
                    <input type="hidden" name="remove" value="1">
                    <button type="submit" class="min-h-11 rounded border border-red-800 px-4 text-red-800">Remove header image</button>
                </form>
            @endif
        </section>
    @endif
@endsection
