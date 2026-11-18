{{-- Search results. Expects $result (App\Search\SearchResult). --}}
<p role="status" class="mb-4 font-medium">{{ trans_choice(':count home found|:count homes found', $result->total()) }}</p>

@if ($result->featured->isNotEmpty())
    <section aria-labelledby="featured-results-heading" class="mb-8">
        <h2 id="featured-results-heading" class="mb-3 text-lg font-semibold">{{ __('Featured') }}</h2>
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($result->featured as $listing)
                <li><x-listing-card :listing="$listing" :featured="true" /></li>
            @endforeach
        </ul>
    </section>
@endif

@if ($result->total() === 0)
    <p>{{ __('No homes match your search. Try removing a filter.') }}</p>
@else
    <h2 class="sr-only">{{ __('All matching homes') }}</h2>
    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($result->listings as $listing)
            <li><x-listing-card :listing="$listing" /></li>
        @endforeach
    </ul>

    <div class="mt-8">{{ $result->listings->links() }}</div>
@endif
