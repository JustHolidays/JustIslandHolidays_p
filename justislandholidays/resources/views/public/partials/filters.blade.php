{{-- Search filters. Expects $filters, $result, $amenities, and optionally $islands (search page only). --}}
<form method="GET" action="{{ $action }}" class="rounded-lg border border-slate-200 bg-white p-4" aria-label="{{ __('Filter homes') }}">
    @if (isset($islands))
        <div class="mb-4">
            <label for="f-island" class="mb-1 block font-medium">{{ __('Island') }}</label>
            <select id="f-island" name="island" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                <option value="">{{ __('All islands') }}</option>
                @foreach ($islands as $i)
                    <option value="{{ $i->slug }}" @selected($filters->island?->id === $i->id)>{{ $i->translate('name') }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="mb-4">
        <label for="f-q" class="mb-1 block font-medium">{{ __('Name or keyword') }}</label>
        <input id="f-q" name="q" type="search" value="{{ $filters->text }}" class="min-h-11 w-full rounded border border-slate-400 px-3">
    </div>

    <fieldset class="mb-4">
        <legend class="mb-1 font-medium">{{ __('Property type') }}</legend>
        @foreach (\App\Models\PropertyType::activeCodes() as $type)
            <div class="flex min-h-11 items-center gap-2">
                <input id="f-type-{{ $type }}" type="checkbox" name="type[]" value="{{ $type }}" class="h-5 w-5" @checked(in_array($type, $filters->types, true))>
                <label for="f-type-{{ $type }}">{{ \App\Models\PropertyType::label($type) }} <span class="text-sm text-slate-600">({{ $result->typeCounts[$type] ?? 0 }})</span></label>
            </div>
        @endforeach
    </fieldset>

    <div class="mb-4 grid grid-cols-2 gap-3">
        <div>
            <label for="f-sleeps" class="mb-1 block font-medium">{{ __('Sleeps') }}</label>
            <select id="f-sleeps" name="sleeps" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                <option value="">{{ __('Any') }}</option>
                @foreach ([2, 4, 6, 8, 10, 12] as $n)
                    <option value="{{ $n }}" @selected($filters->sleeps === $n)>{{ $n }}+</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-bedrooms" class="mb-1 block font-medium">{{ __('Bedrooms') }}</label>
            <select id="f-bedrooms" name="bedrooms" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                <option value="">{{ __('Any') }}</option>
                @foreach ([1, 2, 3, 4, 5, 6] as $n)
                    <option value="{{ $n }}" @selected($filters->bedrooms === $n)>{{ $n }}+</option>
                @endforeach
            </select>
        </div>
    </div>

    <fieldset class="mb-4">
        <legend class="mb-1 font-medium">{{ __('Guide price per week') }}</legend>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="f-price-min" class="mb-1 block text-sm">{{ __('From') }}</label>
                <input id="f-price-min" name="price_min" type="number" min="0" step="50" inputmode="numeric" value="{{ $filters->priceMin }}" class="min-h-11 w-full rounded border border-slate-400 px-3">
            </div>
            <div>
                <label for="f-price-max" class="mb-1 block text-sm">{{ __('To') }}</label>
                <input id="f-price-max" name="price_max" type="number" min="0" step="50" inputmode="numeric" value="{{ $filters->priceMax }}" class="min-h-11 w-full rounded border border-slate-400 px-3">
            </div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend class="mb-1 font-medium">{{ __('Must have') }}</legend>
        <div class="flex min-h-11 items-center gap-2">
            <input id="f-pool" type="checkbox" name="pool" value="1" class="h-5 w-5" @checked($filters->pool)>
            <label for="f-pool">{{ __('Private pool') }}</label>
        </div>
        <div class="flex min-h-11 items-center gap-2">
            <input id="f-pets" type="checkbox" name="pets" value="1" class="h-5 w-5" @checked($filters->pets)>
            <label for="f-pets">{{ __('Pets allowed') }}</label>
        </div>
        @foreach ($amenities as $amenity)
            <div class="flex min-h-11 items-center gap-2">
                <input id="f-amenity-{{ $amenity->code }}" type="checkbox" name="amenity[]" value="{{ $amenity->code }}" class="h-5 w-5" @checked(in_array($amenity->code, $filters->amenities, true))>
                <label for="f-amenity-{{ $amenity->code }}">{{ $amenity->translate('name') }}</label>
            </div>
        @endforeach
    </fieldset>

    <div class="mb-4">
        <label for="f-sort" class="mb-1 block font-medium">{{ __('Sort by') }}</label>
        <select id="f-sort" name="sort" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
            @foreach (['recommended' => __('Recommended'), 'price_asc' => __('Price, low to high'), 'price_desc' => __('Price, high to low'), 'newest' => __('Newest'), 'sleeps' => __('Most guests')] as $value => $label)
                <option value="{{ $value }}" @selected($filters->sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if (request()->query('view') === 'map')
        <input type="hidden" name="view" value="map">
    @endif
    <button type="submit" class="min-h-11 w-full rounded bg-[var(--colour-button)] px-5 font-medium text-white">{{ __('Show homes') }}</button>
    @if ($filters->hasFilters())
        <p class="mt-3 text-center"><a href="{{ $action }}" class="inline-flex min-h-11 items-center underline">{{ __('Clear filters') }}</a></p>
    @endif
</form>
