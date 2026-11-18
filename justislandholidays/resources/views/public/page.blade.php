@extends('layouts.public', [
    'metaTitle' => ($translation->meta_title ?: $translation->title).' | Just Island Holidays',
    'metaDescription' => $translation->meta_description,
    'canonical' => route('pages.show', $page->slug),
])

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ $translation->title }}</h1>
        @if ($isFallback)
            <p class="mt-2 inline-block rounded bg-slate-100 px-2 py-1 text-sm">{{ __('Shown in English') }}</p>
        @endif
        <div class="prose mt-6 max-w-none">{!! $html !!}</div>
    </article>
@endsection
