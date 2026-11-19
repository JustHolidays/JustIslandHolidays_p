@extends('layouts.admin', ['title' => $ad->exists ? 'Edit advert' : 'Add an advert'])

@php
    $input = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
    $chosenSection = (string) old('section', $section);
    $chosenIsland = (int) old('island_id', $ad->island_id);
@endphp

@section('content')
    <p><a href="{{ route('admin.ads.index', array_filter(['island' => $ad->island_id])) }}" class="inline-flex min-h-11 items-center underline">Back to Affiliate Adverts</a></p>
    <h1 class="text-2xl font-semibold">{{ $ad->exists ? 'Edit advert' : 'Add an advert' }}</h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_18rem]">
        <form method="POST" action="{{ $ad->exists ? route('admin.ads.update', $ad) : route('admin.ads.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @if ($ad->exists) @method('PUT') @endif

            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="where-heading">
                <h2 id="where-heading" class="text-lg font-semibold">Where it shows</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="island_id" class="mb-1 block font-medium">Island</label>
                        <select id="island_id" name="island_id" required class="{{ $input }}">
                            <option value="">Choose an island</option>
                            @foreach ($islands as $i)
                                <option value="{{ $i->id }}" @selected($chosenIsland === $i->id)>{{ $i->translate('name', 'en') }}{{ $i->is_active ? '' : ' (switched off)' }}</option>
                            @endforeach
                        </select>
                        @error('island_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="section" class="mb-1 block font-medium">Section on the page</label>
                        <select id="section" name="section" required aria-describedby="section-hint" class="{{ $input }}">
                            @foreach (\App\Ads\IslandAdverts::SECTION_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected($chosenSection === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p id="section-hint" class="mt-1 text-sm text-slate-600">
                            <strong>Menu Advert Section:</strong> 4 banners below the introduction on the island page and its resort pages. With more than 4, 4 are picked at random on each visit.
                            <strong>Sidebar Section:</strong> property pages on the island, below Check availability. Every advert shows.
                            <strong>Both:</strong> in both sections.
                        </p>
                        @error('section')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="advert-heading">
                <h2 id="advert-heading" class="text-lg font-semibold">The advert</h2>
                <div class="mt-3">
                    <x-field name="advertiser" label="Advertiser" :value="$ad->advertiser?->name" hint="The business name. A new name adds a new advertiser." required list="advertiser-names" autocomplete="off" />
                    <datalist id="advertiser-names">@foreach ($advertisers as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
                </div>

                <div class="mb-4">
                    <label for="image" class="mb-1 block font-medium">{{ $ad->exists ? 'Replace the image (optional)' : 'Image' }}</label>
                    <p id="image-hint" class="mb-1 text-sm text-slate-600">JPG, PNG or WebP, at least {{ \App\Ads\IslandAdverts::WIDTH }} × {{ \App\Ads\IslandAdverts::HEIGHT }} pixels, up to 5 MB. It is shown 75 pixels tall and cropped from the centre to a banner 4 wide by 1 tall, so keep words and logos near the middle. A 600 × 150 image looks sharpest.</p>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="image-hint" class="block min-h-11" @unless ($ad->exists) required @endunless>
                    @error('image')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>

                <x-field name="alt" label="Description (alt text)" :value="$ad->alt" hint="What the advert says or shows, for people who cannot see it. For example: Island Car Hire, 10% off for Just Island Holidays guests." required />
                <x-field name="target_url" label="Links to" type="url" :value="$ad->target_url" hint="The advertiser's web page, starting with https://" required />
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="when-heading">
                <h2 id="when-heading" class="text-lg font-semibold">When it shows</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <x-field name="starts_at" label="From" type="date" :value="$ad->starts_at?->toDateString()" required />
                    <x-field name="ends_at" label="Until (not including this day)" type="date" :value="$ad->ends_at?->toDateString()" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="status" class="mb-1 block font-medium">Status</label>
                        <select id="status" name="status" class="{{ $input }}">
                            <option value="active" @selected(old('status', $ad->status) === 'active')>Active</option>
                            <option value="paused" @selected(old('status', $ad->status) === 'paused')>Paused (hidden)</option>
                        </select>
                    </div>
                    <x-field name="weight" label="How often it is picked" type="number" min="1" max="100" :value="$ad->weight" hint="Only matters when the Menu Advert Section has more than 4 adverts: one with 2 is picked twice as often as one with 1." required />
                </div>
            </section>

            <x-button>{{ $ad->exists ? 'Save advert' : 'Add advert' }}</x-button>
        </form>

        <aside aria-label="Preview">
            <h2 class="mb-2 font-semibold">{{ $ad->exists ? 'Current image' : 'Until you add one' }}</h2>
            @if ($ad->exists)
                <img src="{{ \App\Ads\IslandAdverts::imageUrl($ad) }}" alt="{{ $ad->alt }}" width="300" height="75" class="h-[75px] w-full rounded object-cover shadow-sm">
            @else
                <img src="{{ \App\Ads\IslandAdverts::placeholderUrl() }}" alt="The Advertise here placeholder" width="300" height="75" class="h-[75px] w-full rounded object-cover shadow-sm">
                <p class="mt-2 text-sm">The Menu Advert Section shows this placeholder in any space without a running advert.</p>
            @endif

            @if ($ad->exists)
                <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" class="mt-6" onsubmit="return confirm('Delete this advert for good?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="min-h-11 rounded border border-red-800 px-4 text-red-800">Delete advert</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
