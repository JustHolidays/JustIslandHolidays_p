@extends('layouts.public', [
    'metaTitle' => __('Search holiday homes | Just Island Holidays'),
    'canonical' => route('search'),
    'noindex' => true,
])

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ __('Search holiday homes') }}</h1>

        <div class="mt-8 grid gap-8 lg:grid-cols-[18rem_1fr]">
            <div>@include('public.partials.filters', ['action' => route('search')])</div>
            <div>@include('public.partials.results')</div>
        </div>
    </div>
@endsection
