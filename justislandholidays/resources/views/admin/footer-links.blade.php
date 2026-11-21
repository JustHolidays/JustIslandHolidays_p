@extends('layouts.admin', ['title' => 'Footer links'])

@section('content')
    <h1 class="text-2xl font-semibold">Footer links</h1>
    <p class="mt-1 max-w-3xl">The orange buttons in the footer of every public page. Change their labels and order, point them at a content page, a built-in page or another website, and add or remove links (up to {{ \App\Support\FooterLinks::MAX_LINKS }}). To change what a content page says, edit it under <a href="{{ route('admin.pages.index') }}" class="underline">Content pages</a>.</p>
    <p class="mt-1 text-sm">{{ $customised ? 'You have changed these links.' : 'These are the standard links.' }} A link to a content page that is not published is left out of the footer until the page is published.</p>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.footer-links.update') }}" class="mt-6 space-y-4" data-footer-links-form>
        @csrf @method('PUT')
        @foreach ($rows as $i => $row)
            @php
                $current = old("links.{$i}.goes_to", $row['type'] === 'url' ? 'url' : ($row['type'] ? $row['type'].':'.$row['target'] : ''));
                $isSpare = $row['type'] === '';
            @endphp
            <fieldset class="rounded-lg bg-white p-4 shadow-sm" data-footer-row>
                <legend class="px-1 font-semibold">{{ $isSpare ? 'New link' : 'Link '.($i + 1) }}</legend>
                <div class="grid gap-3 md:grid-cols-[6rem_1fr_1fr]">
                    <div>
                        <label for="l{{ $i }}-position" class="mb-1 block text-sm font-medium">Position</label>
                        <input id="l{{ $i }}-position" name="links[{{ $i }}][position]" type="number" min="1" max="99" value="{{ old("links.{$i}.position", $i + 1) }}" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-2">
                    </div>
                    <div>
                        <label for="l{{ $i }}-goes" class="mb-1 block text-sm font-medium">Goes to</label>
                        <select id="l{{ $i }}-goes" name="links[{{ $i }}][goes_to]" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-2">
                            <option value="">{{ $isSpare ? 'Choose… (leave empty for no new link)' : 'Choose…' }}</option>
                            <optgroup label="Content pages">
                                @foreach ($pages as $slug => $title)<option value="page:{{ $slug }}" @selected($current === "page:{$slug}")>{{ $title }} (/{{ $slug }})</option>@endforeach
                            </optgroup>
                            <optgroup label="Built-in pages">
                                @foreach (\App\Support\FooterLinks::BUILT_IN as $key => [$route, $standard])<option value="builtin:{{ $key }}" @selected($current === "builtin:{$key}")>{{ $standard }}</option>@endforeach
                            </optgroup>
                            <optgroup label="Elsewhere">
                                <option value="url" @selected($current === 'url')>Another website (enter the address)</option>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label for="l{{ $i }}-url" class="mb-1 block text-sm font-medium">Web address <span class="font-normal">(only for another website)</span></label>
                        <input id="l{{ $i }}-url" name="links[{{ $i }}][url]" type="url" placeholder="https://" value="{{ old("links.{$i}.url", $row['type'] === 'url' ? $row['target'] : '') }}" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-2">
                    </div>
                </div>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    @foreach ($languages as $lang)
                        <div>
                            <label for="l{{ $i }}-label-{{ $lang }}" class="mb-1 block text-sm font-medium">Label{{ count($languages) > 1 ? ' ('.(['en' => 'English', 'es' => 'Spanish', 'de' => 'German'][$lang] ?? $lang).')' : '' }}</label>
                            <input id="l{{ $i }}-label-{{ $lang }}" name="links[{{ $i }}][label][{{ $lang }}]" maxlength="40" value="{{ old("links.{$i}.label.{$lang}", $row['label'][$lang] ?? '') }}" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-2">
                        </div>
                    @endforeach
                </div>
                @unless ($isSpare)
                    <div class="mt-3 flex min-h-11 items-center gap-2">
                        <input id="l{{ $i }}-remove" name="links[{{ $i }}][remove]" type="checkbox" value="1" class="h-5 w-5" @checked(old("links.{$i}.remove"))>
                        <label for="l{{ $i }}-remove">Remove this link</label>
                    </div>
                @endunless
            </fieldset>
        @endforeach
        <div class="flex flex-wrap items-center gap-3">
            <x-button>Save footer links</x-button>
            <span class="text-sm text-slate-700">Links are shown in order of their Position numbers.</span>
        </div>
    </form>

    @if ($customised)
        <form method="POST" action="{{ route('admin.footer-links.update') }}" class="mt-4" onsubmit="return confirm('Put back the standard footer links? Your changes here are lost.')">
            @csrf @method('PUT')
            <input type="hidden" name="reset" value="1">
            <button type="submit" class="min-h-11 rounded border border-slate-400 bg-white px-4">Use the standard links</button>
        </form>
    @endif

    <section class="mt-8 max-w-3xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="preview-heading">
        <h2 id="preview-heading" class="text-lg font-semibold">What the footer shows now</h2>
        <ol class="mt-2 list-decimal space-y-1 pl-5" data-footer-preview>
            @forelse ($preview as $link)
                <li>{{ $link['label'] }} <span class="text-sm text-slate-600">→ {{ $link['url'] }}</span></li>
            @empty
                <li>No links.</li>
            @endforelse
        </ol>
    </section>
@endsection
