@extends('layouts.admin', ['title' => $page->exists ? 'Edit page' : 'Add a page'])

@php
    $t = $page->exists ? $page->translations->firstWhere('lang', $lang) : null;
    $isSuper = auth()->user()->isSuperAdmin();
    $field = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
@endphp

@section('content')
    <p><a href="{{ route('admin.pages.index') }}" class="inline-flex min-h-11 items-center underline">Back to content pages</a></p>
    <h1 class="text-2xl font-semibold">{{ $page->exists ? ($page->translations->firstWhere('lang', 'en')?->title ?? $page->slug) : 'Add a page' }}</h1>

    @if ($page->exists)
        <nav aria-label="Language" class="mt-4">
            <ul class="flex gap-2" role="list">
                @foreach ($langs as $code => $label)
                    <li><a href="{{ route('admin.pages.edit', [$page, 'lang' => $code]) }}" @class(['inline-flex min-h-11 items-center rounded-t border border-b-0 px-4', 'bg-white font-semibold' => $lang === $code, 'bg-slate-200' => $lang !== $code]) @if ($lang === $code) aria-current="page" @endif>
                        {{ $label }} @unless ($page->translations->firstWhere('lang', $code))<span class="ml-1 text-xs">(not written)</span>@endunless</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-6 rounded-lg bg-white p-5 shadow-sm xl:grid-cols-2">
        @csrf
        @if ($page->exists) @method('PUT') @endif
        <input type="hidden" name="lang" value="{{ $lang }}">

        <div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="slug" label="Web address part" :value="$page->slug" hint="For example about or best-beaches-menorca." required />
                <div class="mb-4"><label for="island_id" class="mb-1 block font-medium">Island</label>
                    <select id="island_id" name="island_id" class="{{ $field }}">
                        @if ($isSuper)<option value="">Site-wide (no island)</option>@endif
                        @foreach ($islands as $i)<option value="{{ $i->id }}" @selected((int) old('island_id', $page->island_id) === $i->id)>{{ $i->translate('name', 'en') }}</option>@endforeach
                    </select></div>
                <div class="mb-4"><label for="kind" class="mb-1 block font-medium">Kind</label>
                    <select id="kind" name="kind" class="{{ $field }}"><option value="guide" @selected(old('kind', $page->kind) === 'guide')>Guide</option>@if ($isSuper)<option value="static" @selected(old('kind', $page->kind) === 'static')>Static (about, terms, privacy)</option>@endif</select></div>
                <div class="mb-4 flex min-h-11 items-center gap-2 pt-6"><input type="hidden" name="is_published" value="0"><input id="is_published" type="checkbox" name="is_published" value="1" class="h-5 w-5" @checked(old('is_published', $page->is_published))><label for="is_published">Published</label></div>
            </div>

            <h2 class="mb-2 text-lg font-semibold">{{ $langs[$lang] }} text</h2>
            <x-field name="title" label="Title" :value="$t?->title" maxlength="190" required />
            <div class="mb-4">
                <label for="body_md" class="mb-1 block font-medium">Text</label>
                <p id="body_md-hint" class="mb-1 text-sm text-slate-600">Markdown: **bold**, ## heading, - list item, [link text](https://…). HTML is removed.</p>
                <textarea id="body_md" name="body_md" rows="18" required aria-describedby="body_md-hint" class="block w-full rounded border border-slate-400 px-3 py-2 font-mono text-sm">{{ old('body_md', $t?->body_md) }}</textarea>
            </div>
            <x-field name="meta_title" label="Search engine title (optional)" :value="$t?->meta_title" maxlength="190" />
            <x-field name="meta_description" label="Search engine description (optional)" :value="$t?->meta_description" maxlength="300" />
            <x-button>Save</x-button>
        </div>

        <section aria-labelledby="preview-heading" aria-live="polite">
            <h2 id="preview-heading" class="mb-2 text-lg font-semibold">Preview</h2>
            <div id="md-preview" class="prose max-w-none rounded border border-slate-200 p-4"><p class="text-slate-500">The preview appears as you type.</p></div>
        </section>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const box = document.getElementById('body_md');
            const out = document.getElementById('md-preview');
            const token = document.querySelector('input[name=_token]').value;
            let timer;
            const render = () => fetch(@js(route('admin.pages.preview')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ body: box.value }),
            }).then(r => r.json()).then(d => { out.innerHTML = d.html || '<p class="text-slate-500">Nothing to preview.</p>'; }).catch(() => {});
            box.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(render, 400); });
            if (box.value) render();
        })();
    </script>
@endpush
