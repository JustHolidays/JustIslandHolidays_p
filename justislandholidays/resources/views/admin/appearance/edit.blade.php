@extends('layouts.admin', ['title' => 'Appearance'])

@section('content')
    <h1 class="text-2xl font-semibold">Appearance</h1>
    <p class="mt-1 max-w-3xl">Changes are saved as a draft. <a href="{{ route('home', ['lang' => 'en', 'preview' => 'draft']) }}" target="_blank" rel="noopener" class="underline">Preview the draft on the home page</a> (new tab), then publish. Every publish is recorded and the last one can be undone.</p>

    <section class="mt-4 flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Publish">
        @if ($hasDraft)
            <p class="font-medium">You have draft changes.</p>
            <form method="POST" action="{{ route('admin.appearance.publish') }}">@csrf<button @disabled(! $canPublish) class="min-h-11 rounded bg-green-800 px-4 font-medium text-white disabled:opacity-50">Publish</button></form>
            <form method="POST" action="{{ route('admin.appearance.discard') }}" onsubmit="return confirm('Throw away all draft changes?')">@csrf<button class="min-h-11 rounded border border-slate-400 px-4">Discard draft</button></form>
            @unless ($canPublish)<p class="font-medium text-red-800">Publishing is blocked until the text and button colours pass the contrast check.</p>@endunless
        @else
            <p>No draft changes. The site shows the published settings.</p>
        @endif
        @if ($lastPublish)
            <form method="POST" action="{{ route('admin.appearance.undo') }}" onsubmit="return confirm('Put back the settings from before the last publish?')" class="ml-auto">
                @csrf<button class="min-h-11 rounded border border-slate-400 px-4">Undo last publish ({{ $lastPublish->created_at->format('j M H:i') }}, {{ $lastPublish->actor?->name }})</button>
            </form>
        @endif
    </section>

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="colours-heading">
        <h2 id="colours-heading" class="text-lg font-semibold">Colours</h2>
        <form method="POST" action="{{ route('admin.appearance.colours') }}" class="mt-3">
            @csrf @method('PUT')
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach (\App\Support\Appearance::COLOUR_NAMES as $name => $label)
                    @php
                        $check = $checks[$name];
                    @endphp
                    <div class="rounded border border-slate-200 p-3">
                        <label for="c-{{ $name }}" class="block font-medium">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="color" value="{{ $colours[$name] }}" aria-label="{{ $label }} colour picker" class="h-11 w-14 cursor-pointer rounded border border-slate-400"
                                   oninput="document.getElementById('c-{{ $name }}').value = this.value">
                            <input id="c-{{ $name }}" name="{{ $name }}" type="text" value="{{ old($name, $colours[$name]) }}" pattern="#[0-9a-fA-F]{6}" class="min-h-11 w-28 rounded border border-slate-400 px-2 font-mono" required>
                        </div>
                        <p @class(['mt-2 text-sm', 'font-semibold text-red-800' => $check['required'] && ! $check['passes'], 'text-green-800' => $check['passes']])>
                            Contrast with {{ $check['against'] }}: {{ number_format($check['ratio'], 2) }} to 1
                            {{ $check['passes'] ? '– passes' : ($check['required'] ? '– too low, needs 4.5' : '– low') }}
                        </p>
                        <p class="text-xs text-slate-600">Published: <span class="font-mono">{{ $published[$name] }}</span></p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button>Save colours as draft</x-button>
                <button type="submit" name="reset" value="1" class="min-h-11 rounded border border-slate-400 px-4" formnovalidate>Back to the original colours</button>
            </div>
        </form>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="logo-heading">
            <h2 id="logo-heading" class="text-lg font-semibold">Logo</h2>
            <p class="mt-1 text-sm">PNG, up to 1 MB. Shown about 40 pixels tall in place of the brand name. Without a logo, the name shows as text.</p>
            @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $logo['alt'] ?? '' }}" class="mt-3 h-10 w-auto">@endif
            <form method="POST" action="{{ route('admin.appearance.logo') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <label for="logo" class="block font-medium">Logo file</label>
                <input id="logo" name="logo" type="file" accept="image/png" class="block min-h-11">
                <x-field name="alt" label="Description (alt text)" :value="$logo['alt'] ?? 'Just Island Holidays'" />
                <div class="flex flex-wrap gap-2">
                    <x-button>Save as draft</x-button>
                    @if ($logoUrl)<button type="submit" name="remove" value="1" class="min-h-11 rounded border border-slate-400 px-4">Remove logo</button>@endif
                </div>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="favicon-heading">
            <h2 id="favicon-heading" class="text-lg font-semibold">Favicon</h2>
            <p class="mt-1 text-sm">A square PNG of at least 512 pixels. The smaller sizes browsers and phones need are made for you.</p>
            @if ($favicons)<div class="mt-3 flex items-end gap-3">@foreach ($favicons as $size => $url)<img src="{{ $url }}" alt="Favicon at {{ $size }} pixels" width="{{ min($size, 64) }}" height="{{ min($size, 64) }}">@endforeach</div>@endif
            <form method="POST" action="{{ route('admin.appearance.favicon') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <label for="favicon" class="block font-medium">Favicon file</label>
                <input id="favicon" name="favicon" type="file" accept="image/png" class="block min-h-11" required>
                <x-button>Save as draft</x-button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="hero-heading">
        <h2 id="hero-heading" class="text-lg font-semibold">Home page hero images</h2>
        <p class="mt-1 text-sm">Up to five switched on. JPG or WebP, at least 2400 pixels wide, up to 5 MB. Phone and tablet sizes are made for you.</p>

        <form method="POST" action="{{ route('admin.appearance.hero.upload') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3 rounded border border-slate-200 p-3">
            @csrf
            <div><label for="hero" class="block font-medium">New image</label><input id="hero" name="hero" type="file" accept="image/jpeg,image/webp" class="block min-h-11" required></div>
            <div class="min-w-64 flex-1"><x-field name="alt_text" label="Description (alt text)" required /></div>
            <x-button>Upload</x-button>
        </form>

        @if ($heroes->isNotEmpty())
            <form method="POST" action="{{ route('admin.appearance.hero.arrange') }}" class="mt-4">
                @csrf @method('PUT')
                <fieldset class="mb-4">
                    <legend class="font-medium">Which image to show</legend>
                    <div class="flex min-h-11 items-center gap-2"><input id="mode-random" type="radio" name="mode" value="random" class="h-5 w-5" @checked($heroMode === 'random')><label for="mode-random">A different one at random on each visit</label></div>
                    <div class="flex min-h-11 items-center gap-2"><input id="mode-first" type="radio" name="mode" value="first" class="h-5 w-5" @checked($heroMode === 'first')><label for="mode-first">Always the first one</label></div>
                </fieldset>
                <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($heroes as $hero)
                        @php
                            $d = $heroDraft[$hero->id] ?? ['active' => $hero->is_active, 'sort' => $hero->sort_order, 'alt_text' => $hero->alt_text];
                        @endphp
                        <li class="rounded border border-slate-200 p-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(str_replace('-2400.webp', '-800.webp', $hero->image_path)) }}" alt="{{ $hero->alt_text }}" class="aspect-[16/7] w-full rounded object-cover" width="800" height="350">
                            <p class="mt-1 text-xs text-slate-600">Live now: {{ $hero->is_active ? 'on' : 'off' }}</p>
                            <div class="mt-2 flex min-h-11 items-center gap-2"><input type="hidden" name="heroes[{{ $hero->id }}][active]" value="0"><input id="h-on-{{ $hero->id }}" type="checkbox" name="heroes[{{ $hero->id }}][active]" value="1" class="h-5 w-5" @checked($d['active'])><label for="h-on-{{ $hero->id }}">Switched on</label></div>
                            <label for="h-sort-{{ $hero->id }}" class="block text-sm font-medium">Order</label>
                            <input id="h-sort-{{ $hero->id }}" type="number" min="0" max="255" name="heroes[{{ $hero->id }}][sort]" value="{{ $d['sort'] }}" class="min-h-11 w-24 rounded border border-slate-400 px-2">
                            <label for="h-alt-{{ $hero->id }}" class="mt-2 block text-sm font-medium">Description (alt text)</label>
                            <input id="h-alt-{{ $hero->id }}" type="text" maxlength="160" name="heroes[{{ $hero->id }}][alt_text]" value="{{ $d['alt_text'] ?? $hero->alt_text }}" class="min-h-11 w-full rounded border border-slate-400 px-2" required>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4"><x-button>Save hero images as draft</x-button></div>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($heroes->where('is_active', false) as $hero)
                    <form method="POST" action="{{ route('admin.appearance.hero.remove', $hero) }}" onsubmit="return confirm('Delete this image for good?')">@csrf @method('DELETE')<button class="min-h-11 rounded border border-red-800 px-3 text-sm text-red-800">Delete “{{ \Illuminate\Support\Str::limit($hero->alt_text, 30) }}”</button></form>
                @endforeach
            </div>
        @endif
    </section>
@endsection
