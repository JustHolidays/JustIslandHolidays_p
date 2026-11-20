@extends('layouts.account', ['title' => 'My favourites'])

@section('content')
    <h1 class="text-2xl font-semibold">My favourites</h1>
    <p class="mt-1">The holiday homes you saved with the heart. Press the heart on any home to add it here.</p>

    @if ($listings->isEmpty())
        <p class="mt-6">No favourites yet. <a href="{{ route('search', 'en') }}" class="underline">Find a holiday home</a> and press its heart to save it.</p>
    @else
        <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                @php($available = ! $listing->trashed() && $listing->status === \App\Enums\ListingStatus::Live && $listing->island?->is_active)
                <li class="flex flex-col" data-favourite-listing="{{ $listing->id }}">
                    @if ($available)
                        <x-listing-card :listing="$listing" :heading-level="2" />
                    @else
                        <div class="flex h-full flex-col justify-center rounded-lg border border-slate-200 bg-white p-4">
                            <h2 class="text-lg font-semibold">{{ $listing->title('en') }}</h2>
                            <p class="mt-1 text-sm text-slate-700">No longer listed. It may come back later.</p>
                        </div>
                    @endif
                    <form data-keep-scroll method="POST" action="{{ route('account.favourites.destroy', $listing->id) }}" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="min-h-11 text-red-800 underline">Remove<span class="sr-only"> {{ $listing->title('en') }}</span> from favourites</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
