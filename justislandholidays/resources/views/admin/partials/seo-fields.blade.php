{{--
    Title and description boxes for one page (or one island or resort) in one language, with
    live character counts and a search-result preview. Empty boxes show the wording in use
    greyed: this page type's saved wording, else the site's built-in wording.
--}}
@php
    $type = \App\Support\Seo::type($key);
    $inherit = $key === $type ? \App\Support\Seo::PAGES[$type] : ($saved[$lang][$type] ?? []) + \App\Support\Seo::PAGES[$type];
    $id = str_replace(':', '-', $key).'-'.$lang;
@endphp
<div class="mt-3 grid gap-3 lg:grid-cols-[1fr_22rem]" data-seo-fields data-examples="{{ json_encode($examples) }}">
    <div class="space-y-2">
        @if ($langName)<p class="text-sm font-semibold">{{ $langName }}</p>@endif
        <div>
            <label for="title-{{ $id }}" class="mb-1 block text-sm font-medium">Title</label>
            <input id="title-{{ $id }}" name="meta[{{ $key }}][{{ $lang }}][title]" type="text" maxlength="190" data-seo-title
                   value="{{ old('meta.'.$key.'.'.$lang.'.title', $values['title'] ?? '') }}" placeholder="{{ ($inherit['title'] ?? '') ?: '' }}"
                   aria-describedby="count-title-{{ $id }}" class="block min-h-11 w-full rounded border border-slate-400 px-3">
            <p id="count-title-{{ $id }}" class="mt-0.5 text-xs text-slate-600" data-count-title></p>
        </div>
        <div>
            <label for="description-{{ $id }}" class="mb-1 block text-sm font-medium">Description</label>
            <textarea id="description-{{ $id }}" name="meta[{{ $key }}][{{ $lang }}][description]" rows="2" maxlength="300" data-seo-description
                      placeholder="{{ ($inherit['description'] ?? '') ?: '' }}" aria-describedby="count-description-{{ $id }}"
                      class="block w-full rounded border border-slate-400 px-3 py-2">{{ old('meta.'.$key.'.'.$lang.'.description', $values['description'] ?? '') }}</textarea>
            <p id="count-description-{{ $id }}" class="mt-0.5 text-xs text-slate-600" data-count-description></p>
        </div>
    </div>
    <div class="rounded border border-slate-200 bg-slate-50 p-3 text-sm" aria-hidden="true">
        <p class="text-xs text-slate-600">Search result preview</p>
        <p class="mt-1 truncate text-base text-[#1a0dab]" data-preview-title></p>
        <p class="line-clamp-2 text-slate-700" data-preview-description></p>
    </div>
</div>
