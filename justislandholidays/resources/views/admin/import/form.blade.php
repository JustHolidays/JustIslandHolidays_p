@extends('layouts.admin', ['title' => 'Add an affiliate listing from a web address'])

@php
    $d = fn ($k, $default = null) => old($k, $draft[$k] ?? $default);
    $field = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
    $fetched = isset($draft['source_url']);
    $needs = fn ($k) => $fetched && ! in_array($k, $draft['found'] ?? [], true);
@endphp

@section('content')
    <p><a href="{{ route('admin.listings.index') }}" class="inline-flex min-h-11 items-center underline">Back to listings</a></p>
    <h1 class="text-2xl font-semibold">Add an affiliate listing from a web address</h1>

    <form method="POST" action="{{ route('admin.import.fetch') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
        @csrf
        <div><label for="f-affiliate" class="block font-medium">Affiliate</label>
            <select id="f-affiliate" name="affiliate_id" class="min-h-11 rounded border border-slate-400 bg-white px-2" required>
                <option value="">Choose…</option>
                @foreach ($affiliates as $a)<option value="{{ $a->id }}" @selected((int) $d('affiliate_id') === $a->id)>{{ $a->name }}</option>@endforeach
            </select></div>
        <div class="min-w-72 flex-1"><label for="f-url" class="block font-medium">Property page address</label>
            <input id="f-url" name="source_url" type="url" value="{{ $d('source_url') }}" placeholder="https://" class="{{ $field }}" required></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Read the page</button>
    </form>

    @if ($error)
        <p role="alert" class="mt-4 rounded border border-amber-300 bg-amber-50 p-4">{{ $error }}</p>
    @elseif ($fetched)
        <p role="status" class="mt-4 rounded border border-green-300 bg-green-50 p-4">Page read. Fields marked “needs input” were not found on the page.</p>
    @endif

    @if ($fetched || old('title'))
        <form method="POST" action="{{ route('admin.import.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 xl:grid-cols-[1fr_24rem]" id="import-form">
            @csrf
            <input type="hidden" name="affiliate_id" value="{{ $d('affiliate_id') }}">
            <input type="hidden" name="source_url" value="{{ $d('source_url') }}">
            <input type="hidden" name="image_url" value="{{ $d('image_url') }}">

            <div class="space-y-6">
                <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="details-heading">
                    <h2 id="details-heading" class="text-lg font-semibold">Details</h2>
                    <x-field name="title" :label="'Title'.($needs('title') ? ' (needs input)' : '')" :value="$d('title')" maxlength="160" required data-preview="title" />
                    <div class="mb-4"><label for="summary" class="mb-1 block font-medium">Short description{{ $needs('summary') ? ' (needs input)' : '' }}</label>
                        <textarea id="summary" name="summary" rows="4" maxlength="500" required data-preview="summary" class="block w-full rounded border border-slate-400 px-3 py-2">{{ $d('summary') }}</textarea></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="mb-4"><label for="area_id" class="mb-1 block font-medium">Island and resort</label>
                            <select id="area_id" name="area_id" class="{{ $field }}" required data-preview="area">
                                <option value="">Choose…</option>
                                @foreach ($islands as $island)<optgroup label="{{ $island->translate('name', 'en') }}">@foreach ($island->areas as $a)<option value="{{ $a->id }}" @selected((int) $d('area_id') === $a->id)>{{ $a->translate('name', 'en') }}, {{ $island->translate('name', 'en') }}</option>@endforeach</optgroup>@endforeach
                            </select></div>
                        <div class="mb-4"><label for="property_type" class="mb-1 block font-medium">Type</label>
                            <select id="property_type" name="property_type" class="{{ $field }}" data-preview="type">@foreach (\App\Models\PropertyType::activeCodes() as $t)<option value="{{ $t }}" @selected($d('property_type', 'villa') === $t)>{{ \App\Models\PropertyType::label($t, 'en') }}</option>@endforeach</select></div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <x-field name="bedrooms" :label="'Bedrooms'.($needs('bedrooms') ? ' (needs input)' : '')" type="number" min="0" :value="$d('bedrooms')" required data-preview="bedrooms" />
                        <x-field name="bathrooms" :label="'Bathrooms'.($needs('bathrooms') ? ' (needs input)' : '')" type="number" min="0" :value="$d('bathrooms')" required />
                        <x-field name="sleeps" :label="'Sleeps'.($needs('sleeps') ? ' (needs input)' : '')" type="number" min="1" max="40" :value="$d('sleeps')" required data-preview="sleeps" />
                    </div>
                    <div class="mb-4 flex min-h-11 items-center gap-2"><input type="hidden" name="has_private_pool" value="0"><input id="has_private_pool" type="checkbox" name="has_private_pool" value="1" class="h-5 w-5" @checked($d('has_private_pool'))><label for="has_private_pool">Private pool</label></div>
                    <div class="grid grid-cols-[1fr_8rem] gap-4">
                        <x-field name="price" label="Guide price per week (optional)" type="number" min="0" :value="$d('price')" data-preview="price" />
                        <div class="mb-4"><label for="currency" class="mb-1 block font-medium">Currency</label><select id="currency" name="currency" class="{{ $field }}" data-preview="currency"><option value="EUR">€</option><option value="GBP" @selected($d('currency') === 'GBP')>£</option></select></div>
                    </div>
                    <x-field name="coordinates" :label="'Map coordinates'.($needs('lat') ? ' (needs input)' : '')" :value="$d('coordinates')" hint="For example 39.9112, 4.2650. Visitors see a pin about 300 m away." required />
                </section>

                <section class="rounded-lg border-2 border-sky-700 bg-white p-5 shadow-sm" aria-labelledby="link-heading">
                    <h2 id="link-heading" class="text-lg font-semibold">Affiliate link (Check availability)</h2>
                    <p class="mt-1 text-sm">The tracking link from the provider's programme, not the page address above. Must start with https://</p>
                    <x-field name="deeplink_url" label="Affiliate link" type="url" :value="$d('deeplink_url')" required data-preview="deeplink" />
                    <x-field name="deeplink_subid_param" label="Click id parameter (optional)" :value="$d('deeplink_subid_param')" hint="If the programme reports a sub-id back, its name, for example subid." />
                    <p class="mb-3"><a id="test-link" href="{{ $d('deeplink_url') ?: '#' }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Test link (new tab, no click recorded)</a></p>
                    <div class="flex items-start gap-2"><input type="hidden" name="link_tested" value="0"><input id="link_tested" type="checkbox" name="link_tested" value="1" class="mt-1 h-5 w-5" @checked($d('link_tested'))><label for="link_tested">I tested the link and it opens this property's booking page.</label></div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="photo-heading">
                    <h2 id="photo-heading" class="text-lg font-semibold">Photo</h2>
                    @if ($d('image_url'))
                        <p class="mt-1 text-sm">Photo found on the page. It is copied to our own storage and resized only if you tick the box.</p>
                        <div class="mt-2 flex items-start gap-2"><input type="hidden" name="image_permission" value="0"><input id="image_permission" type="checkbox" name="image_permission" value="1" class="mt-1 h-5 w-5" @checked($d('image_permission'))><label for="image_permission">We have permission from the provider to use this photo.</label></div>
                    @else
                        <p class="mt-1 text-sm">No photo was found on the page. Upload one.</p>
                    @endif
                    <div class="mt-3"><label for="photo" class="mb-1 block font-medium">{{ $d('image_url') ? 'Or upload a different photo' : 'Photo' }}</label>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block min-h-11"></div>
                    <div class="mt-3"><x-field name="image_alt" label="Describe the photo (alt text)" :value="$d('image_alt')" maxlength="160" required /></div>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button name="save" value="draft" class="min-h-11 rounded border border-slate-400 bg-white px-4">Save as draft</button>
                    <button name="save" value="review" class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Save and send for review</button>
                    <button name="save" value="publish" class="min-h-11 rounded bg-green-800 px-4 font-medium text-white">Approve and publish</button>
                </div>
            </div>

            <aside aria-labelledby="preview-heading" class="xl:sticky xl:top-4 xl:self-start">
                <h2 id="preview-heading" class="mb-2 text-lg font-semibold">Preview</h2>
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="aspect-[4/3] bg-slate-100">
                        @if ($d('image_url'))
                            {{-- Not loaded from the provider (no hot-linking). Our own copy is made on save. --}}
                            <p class="flex h-full flex-col items-center justify-center gap-2 p-4 text-center text-sm text-slate-700">
                                Photo found on the page.
                                <a href="{{ $d('image_url') }}" target="_blank" rel="noopener noreferrer" class="underline">Open it on the provider's site</a>
                            </p>
                        @else
                            <p class="flex h-full items-center justify-center text-sm text-slate-600">No photo</p>
                        @endif
                    </div>
                    <div class="p-4">
                        <p class="text-lg font-semibold" data-out="title">{{ $d('title') }}</p>
                        <p class="text-sm text-slate-700" data-out="area"></p>
                        <p class="mt-2 text-sm"><span data-out="type"></span> · <span data-out="bedrooms"></span> bedrooms · sleeps <span data-out="sleeps"></span></p>
                        <p class="mt-2 text-sm" data-out="summary">{{ $d('summary') }}</p>
                        <p class="mt-2 font-semibold" data-out="price"></p>
                        <p class="mt-3"><span id="preview-button" class="flex min-h-11 items-center justify-center rounded bg-slate-300 px-4 font-semibold text-slate-600">Check availability</span></p>
                    </div>
                </div>
            </aside>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('import-form');
            if (!form) return;
            const val = (k) => form.querySelector(`[data-preview="${k}"]`);
            const out = (k, v) => form.querySelectorAll(`[data-out="${k}"]`).forEach(e => e.textContent = v);
            const update = () => {
                out('title', val('title').value);
                out('summary', val('summary').value);
                const area = val('area'); out('area', area.selectedIndex > 0 ? area.options[area.selectedIndex].text : '');
                const type = val('type'); out('type', type.options[type.selectedIndex].text);
                out('bedrooms', val('bedrooms').value || '?');
                out('sleeps', val('sleeps').value || '?');
                const price = val('price').value; out('price', price ? `From ${val('currency').value === 'GBP' ? '£' : '€'}${Number(price).toLocaleString('en-GB')} a week` : '');
                const link = val('deeplink').value.trim();
                const ok = link.startsWith('https://');
                const btn = document.getElementById('preview-button');
                btn.className = 'flex min-h-11 items-center justify-center rounded px-4 font-semibold ' + (ok ? 'bg-sky-800 text-white' : 'bg-slate-300 text-slate-600');
                document.getElementById('test-link').href = ok ? link : '#';
            };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        })();
    </script>
@endpush
