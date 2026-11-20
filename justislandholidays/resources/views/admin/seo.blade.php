@extends('layouts.admin', ['title' => 'SEO'])

@php
    $examples = ['island' => 'Menorca', 'resort' => 'Cala Galdana', 'count' => 8];
    $lang0 = $langs[0];
    $languageNames = ['en' => 'English', 'es' => 'Spanish', 'de' => 'German'];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">SEO</h1>
    <p class="mt-1 max-w-3xl">How the site appears in search engines and when pages are shared. Only super admins can open this page; every change is written to the audit log.</p>

    @if ($blocked)
        <p role="alert" class="mt-4 max-w-3xl rounded border border-amber-400 bg-amber-50 p-3 font-medium">Search engines are kept out of the whole site (SITE_BLOCK_INDEXING is on in the server's settings). Turn it off at launch, or nothing here will appear in search results.</p>
    @endif
    @error('meta')<p role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-3 font-medium text-red-800">{{ $message }}</p>@enderror

    {{-- 1. Sitemap --}}
    <section id="sitemap" class="mt-6 scroll-mt-4 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="sitemap-heading">
        <h2 id="sitemap-heading" class="text-lg font-semibold">Sitemap</h2>
        <p class="mt-1 text-sm">A list of every public page for search engines: live properties (with their main photo), islands, resorts, published content pages and the main pages, in each language that is switched on. It updates itself at least every hour, and robots.txt points to it. Add its address to Google Search Console and Bing Webmaster Tools.</p>
        <p class="mt-3"><a href="{{ route('sitemap') }}" class="break-all font-mono underline" target="_blank" rel="noopener">{{ route('sitemap') }}</a></p>
        <p class="mt-1 text-sm text-slate-700" data-sitemap-info>
            @if ($sitemap['built_at'])
                Last built {{ \Carbon\Carbon::parse($sitemap['built_at'])->format('j M Y, H:i') }} with {{ number_format($sitemap['urls']) }} addresses.
            @else
                Not built yet: it is built the first time it is opened.
            @endif
        </p>
        <form method="POST" action="{{ route('admin.seo.sitemap') }}" class="mt-3">@csrf<x-button>Rebuild now</x-button></form>
    </section>

    {{-- 2. Titles and descriptions --}}
    <section id="titles" class="mt-6 scroll-mt-4 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="titles-heading">
        <h2 id="titles-heading" class="text-lg font-semibold">Titles and descriptions</h2>
        <p class="mt-1 max-w-3xl text-sm">The title is the blue link in search results and the browser tab; the description is the text under it. Leave a field empty to keep the wording built into the site (shown greyed in the box). About {{ \App\Support\Seo::TITLE_LENGTH }} characters of a title and {{ \App\Support\Seo::DESCRIPTION_LENGTH }} of a description show before search engines cut them off. Property pages use their own title and summary, and content pages are set in Content pages.</p>

        <form method="POST" action="{{ route('admin.seo.meta') }}" class="mt-4 space-y-6">
            @csrf
            @foreach (\App\Support\Seo::PAGES as $key => $page)
                <fieldset class="rounded border border-slate-200 p-4" data-seo-page="{{ $key }}">
                    <legend class="px-1 font-semibold">{{ $page['label'] }}</legend>
                    @if ($page['vars'] !== [])
                        <p class="text-sm text-slate-700">You can use: @foreach ($page['vars'] as $var)<code class="rounded bg-slate-100 px-1">{{ '{'.$var.'}' }}</code>@if (! $loop->last), @endif @endforeach (for example {{ collect($page['vars'])->map(fn ($v) => '{'.$v.'} = '.$examples[$v])->implode(', ') }}).</p>
                    @endif
                    @foreach ($langs as $lang)
                        @include('admin.partials.seo-fields', ['key' => $key, 'lang' => $lang, 'langName' => count($langs) > 1 ? $languageNames[$lang] ?? $lang : null, 'values' => $saved[$lang][$key] ?? [], 'examples' => $examples])
                    @endforeach
                </fieldset>
            @endforeach

            <details class="rounded border border-slate-200 p-4">
                <summary class="flex min-h-11 cursor-pointer items-center font-semibold">One island or resort only</summary>
                <p class="mt-1 text-sm text-slate-700">Wording here replaces the island or resort pages' wording above for that one place. Leave empty to use the wording above.</p>
                @foreach ($islands as $island)
                    <fieldset class="mt-4 rounded border border-slate-200 p-4">
                        <legend class="px-1 font-semibold">{{ $island->translate('name', 'en') }}</legend>
                        @foreach ($langs as $lang)
                            @include('admin.partials.seo-fields', ['key' => 'island:'.$island->id, 'lang' => $lang, 'langName' => count($langs) > 1 ? $languageNames[$lang] ?? $lang : null, 'values' => $saved[$lang]['island:'.$island->id] ?? [], 'examples' => ['island' => $island->translate('name', 'en'), 'count' => 8]])
                        @endforeach
                        @foreach ($areas[$island->id] ?? [] as $area)
                            <details class="mt-3">
                                <summary class="flex min-h-11 cursor-pointer items-center">{{ $area->translate('name', 'en') }}@if (filled($saved[$lang0]['area:'.$area->id]['title'] ?? null) || filled($saved[$lang0]['area:'.$area->id]['description'] ?? null)) <span class="ml-2 rounded bg-slate-100 px-2 text-xs">own wording</span>@endif</summary>
                                @foreach ($langs as $lang)
                                    @include('admin.partials.seo-fields', ['key' => 'area:'.$area->id, 'lang' => $lang, 'langName' => count($langs) > 1 ? $languageNames[$lang] ?? $lang : null, 'values' => $saved[$lang]['area:'.$area->id] ?? [], 'examples' => ['island' => $island->translate('name', 'en'), 'resort' => $area->translate('name', 'en'), 'count' => 8]])
                                @endforeach
                            </details>
                        @endforeach
                    </fieldset>
                @endforeach
            </details>

            <x-button>Save titles and descriptions</x-button>
        </form>
    </section>

    {{-- 3. Sharing and business details --}}
    <section id="sharing" class="mt-6 scroll-mt-4 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="sharing-heading">
        <h2 id="sharing-heading" class="text-lg font-semibold">Sharing previews</h2>
        <p class="mt-1 max-w-3xl text-sm">When a page is shared on Facebook, WhatsApp, LinkedIn and similar, they show a picture with the page's title and description. Property pages use the home's own main photo; every other page uses the picture below. It is cropped to 1200 × 630 pixels.</p>
        @if ($shareImage)
            <img src="{{ $shareImage }}" alt="The default sharing picture" class="mt-3 w-full max-w-md rounded border border-slate-200" width="1200" height="630">
        @else
            <p class="mt-3 text-sm font-medium text-amber-800">No default picture yet: shared pages other than properties show no picture.</p>
        @endif
        @error('share_image')<p class="mt-2 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('admin.seo.share') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label for="share_image" class="mb-1 block font-medium">Picture (at least 1200 × 630, JPG, PNG or WebP)</label>
                <input id="share_image" name="share_image" type="file" accept="image/jpeg,image/png,image/webp" class="block min-h-11">
            </div>
            <x-button>Save picture</x-button>
            @if ($shareImage)<button type="submit" name="remove" value="1" class="min-h-11 rounded border border-slate-400 px-4">Remove picture</button>@endif
        </form>

        <h3 class="mt-6 font-semibold">Business details for search engines</h3>
        <p class="mt-1 text-sm">Shown to search engines on the home page, so they can link the site to the business. Change the name and address in config/site.php; the logo comes from the Frontend Styler.</p>
        <dl class="mt-2 grid max-w-xl gap-1 text-sm sm:grid-cols-[8rem_1fr]">
            <dt class="font-medium">Name</dt><dd>{{ $business['name'] }}</dd>
            <dt class="font-medium">Address</dt><dd>{{ $business['street'] }}, {{ $business['locality'] }}, {{ $business['postcode'] }}, {{ $business['country'] === 'GB' ? 'United Kingdom' : $business['country'] }}</dd>
            <dt class="font-medium">Website</dt><dd>{{ config('app.url') }}</dd>
            <dt class="font-medium">Logo</dt><dd>{{ $logo ? 'From the Frontend Styler' : 'None yet: add one in the Frontend Styler' }}</dd>
        </dl>
    </section>

    {{-- 4. Health check --}}
    <section id="health" class="mt-6 scroll-mt-4 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="health-heading">
        <h2 id="health-heading" class="text-lg font-semibold">Health check</h2>
        <p class="mt-1 text-sm">Things that may hold pages back in search results, for live properties and active islands. Each links to where it is fixed.</p>
        <ul class="mt-3 space-y-3">
            @foreach ($checks as $check)
                <li class="rounded border border-slate-200 p-3" data-check="{{ $check['key'] }}">
                    <p class="font-medium">
                        @if ($check['items']->isEmpty())
                            <span class="text-green-800" aria-hidden="true">✓</span> {{ $check['title'] }}: none
                        @else
                            <span class="text-amber-800" aria-hidden="true">!</span> {{ $check['title'] }}: {{ $check['items']->count() }}
                        @endif
                    </p>
                    @if ($check['items']->isNotEmpty())
                        <p class="text-sm text-slate-700">{{ $check['why'] }}</p>
                        <ul class="mt-1 list-disc pl-5 text-sm">
                            @foreach ($check['items']->take(\App\Support\SeoHealth::LIMIT) as $item)
                                <li><a href="{{ $item['url'] }}" class="underline">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                        @if ($check['items']->count() > \App\Support\SeoHealth::LIMIT)<p class="text-sm">And {{ $check['items']->count() - \App\Support\SeoHealth::LIMIT }} more.</p>@endif
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Live counters and search-result preview for the title and description boxes. --}}
    <script>
        document.querySelectorAll('[data-seo-fields]').forEach((box) => {
            const examples = JSON.parse(box.dataset.examples || '{}');
            const fill = (text) => text.replace(/\{(\w+)\}/g, (m, k) => examples[k] ?? '');
            const title = box.querySelector('[data-seo-title]');
            const desc = box.querySelector('[data-seo-description]');
            const update = () => {
                const t = fill(title.value || title.placeholder);
                const d = fill(desc.value || desc.placeholder);
                box.querySelector('[data-count-title]').textContent = t.length + ' characters' + (t.length > {{ \App\Support\Seo::TITLE_LENGTH }} ? ': may be cut off' : '');
                box.querySelector('[data-count-description]').textContent = d.length + ' characters' + (d.length > {{ \App\Support\Seo::DESCRIPTION_LENGTH }} ? ': may be cut off' : '');
                box.querySelector('[data-preview-title]').textContent = t;
                box.querySelector('[data-preview-description]').textContent = d;
            };
            title.addEventListener('input', update);
            desc.addEventListener('input', update);
            update();
        });
    </script>
@endsection
