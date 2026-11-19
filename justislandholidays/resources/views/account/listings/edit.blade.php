@extends('layouts.account', ['title' => $listing->exists ? 'Edit listing' : 'Add a listing'])

@php
    $t = $listing->exists ? $listing->translationFor('en')[0] : null;
    $private = $listing->private;
    $licence = $listing->licence;
    $stepKeys = array_keys($steps);
    $input = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
    $petsValue = old('pets', $listing->exists ? ($listing->pets_allowed === null ? 'not_stated' : ($listing->pets_allowed ? 'yes' : 'no')) : 'not_stated');
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">{{ $listing->exists ? $listing->title('en') : 'Add a listing' }}</h1>
    @if ($listing->exists)
        <p class="mt-1">Status: <strong>{{ $listing->status->label() }}</strong></p>
    @endif

    <nav aria-label="Steps" class="mt-6">
        <ol class="flex flex-wrap gap-2">
            @foreach ($steps as $key => $label)
                <li>
                    @if ($listing->exists)
                        <a href="{{ route('account.listings.edit', [$listing, $key]) }}"
                           @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] bg-[var(--colour-brand)] text-white' => $step === $key, 'border-slate-300 bg-white' => $step !== $key])
                           @if ($step === $key) aria-current="step" @endif>{{ $loop->iteration }}. {{ $label }}</a>
                    @else
                        <span @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] bg-[var(--colour-brand)] text-white' => $key === 'basics', 'border-slate-300 bg-white text-slate-500' => $key !== 'basics'])
                              @if ($key === 'basics') aria-current="step" @endif>{{ $loop->iteration }}. {{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    @if ($locked)
        <p role="status" class="mt-6 rounded border border-amber-300 bg-amber-50 p-4">
            {{ $listing->status->value === 'archived' ? 'This listing is archived and can no longer be edited.' : 'This listing is waiting for review. You can change it again once we have checked it.' }}
        </p>
    @endif

    <div class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6">
        <fieldset @disabled($locked)>
        @switch($step)
            @case('basics')
                <form method="POST" action="{{ $listing->exists ? route('account.listings.basics', $listing) : route('account.listings.store') }}">
                    @csrf
                    @if ($listing->exists) @method('PUT') @endif
                    <h2 class="mb-4 text-xl font-semibold">Basics</h2>

                    <x-field name="title" label="Title" :value="$t?->title" hint="For example: Villa with sea views near Son Bou. Up to 160 characters." maxlength="160" required />
                    <div class="mb-4">
                        <label for="summary" class="mb-1 block font-medium">Short description</label>
                        <p id="summary-hint" class="mb-1 text-sm text-slate-600">Two or three sentences about the home. Up to 500 characters.</p>
                        <textarea id="summary" name="summary" rows="4" maxlength="500" aria-describedby="summary-hint" class="block w-full rounded border border-slate-400 px-3 py-2" required>{{ old('summary', $t?->summary) }}</textarea>
                    </div>

                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="island_id" class="mb-1 block font-medium">Island</label>
                            <select id="island_id" name="island_id" class="{{ $input }}" required>
                                @foreach ($islands as $island)
                                    <option value="{{ $island->id }}" @selected((int) old('island_id', $listing->island_id) === $island->id)>{{ $island->translate('name', 'en') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="area_id" class="mb-1 block font-medium">Resort or town</label>
                            <select id="area_id" name="area_id" class="{{ $input }}" required>
                                @foreach ($islands as $island)
                                    <optgroup label="{{ $island->translate('name', 'en') }}">
                                        @foreach ($island->areas as $area)
                                            <option value="{{ $area->id }}" @selected((int) old('area_id', $listing->area_id) === $area->id)>{{ $area->translate('name', 'en') }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="property_type" class="mb-1 block font-medium">Type of property</label>
                        <select id="property_type" name="property_type" class="{{ $input }}" required>
                            @foreach (\App\Models\PropertyType::allowedFor($listing->property_type) as $type)
                                <option value="{{ $type }}" @selected(old('property_type', $listing->property_type) === $type)>{{ \App\Models\PropertyType::label($type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <x-field name="bedrooms" label="Bedrooms" type="number" min="0" max="30" :value="$listing->bedrooms" required />
                        <x-field name="bathrooms" label="Bathrooms" type="number" min="0" max="30" :value="$listing->bathrooms" required />
                        <x-field name="sleeps" label="Sleeps" type="number" min="1" max="40" :value="$listing->sleeps" required />
                    </div>

                    <div class="mb-4 flex min-h-11 items-center gap-2">
                        <input type="hidden" name="has_private_pool" value="0">
                        <input id="has_private_pool" name="has_private_pool" type="checkbox" value="1" class="h-5 w-5" @checked(old('has_private_pool', $listing->has_private_pool))>
                        <label for="has_private_pool">Private pool</label>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="mb-1 font-medium">Pets</legend>
                        @foreach (['yes' => 'Pets allowed', 'no' => 'No pets', 'not_stated' => 'Prefer not to say (not shown to visitors)'] as $value => $label)
                            <div class="flex min-h-11 items-center gap-2">
                                <input id="pets_{{ $value }}" name="pets" type="radio" value="{{ $value }}" class="h-5 w-5" @checked($petsValue === $value)>
                                <label for="pets_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>

                    <x-field name="distance_to_beach_m" label="Distance to the nearest beach, in metres (optional)" type="number" min="0" :value="$listing->distance_to_beach_m" />

                    <div class="grid grid-cols-[1fr_8rem] gap-4">
                        <x-field name="price" :label="$isAffiliate ? 'From price per week (optional)' : 'From price per week'" type="number" min="1" step="1" hint="Your lowest weekly price, shown as From Price. You confirm the final price with guests." :required="! $isAffiliate" :value="$listing->min_price_week_cents !== null ? intdiv($listing->min_price_week_cents, 100) : null" />
                        <div class="mb-4">
                            <label for="currency" class="mb-1 block font-medium">Currency</label>
                            <select id="currency" name="currency" class="{{ $input }}">
                                @foreach (['EUR' => 'Euro (€)', 'GBP' => 'Pound (£)'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('currency', $listing->currency ?? 'EUR') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <x-button>Save and continue</x-button>
                </form>
                @break

            @case('location')
                <form method="POST" action="{{ route('account.listings.location', $listing) }}">
                    @csrf @method('PUT')
                    <h2 class="mb-2 text-xl font-semibold">Location and contact</h2>
                    <p class="mb-4">Your exact location and contact details are private. Visitors see a map pin about 300 metres from the home, never the address.</p>

                    <x-field name="coordinates" label="Map coordinates" :value="$private ? rtrim(rtrim($private->lat, '0'), '.').', '.rtrim(rtrim($private->lng, '0'), '.') : null"
                             hint="In Google Maps, right-click on your home and click the numbers at the top to copy them. Paste them here, for example 39.9112, 4.2650." required />
                    <x-field name="address_line" label="Address (optional, private)" :value="$private?->address_line" autocomplete="street-address" />
                    <x-field name="postcode" label="Postcode (optional, private)" :value="$private?->postcode" autocomplete="postal-code" />

                    <h3 class="mb-2 mt-6 text-lg font-semibold">Where should enquiries go?</h3>
                    <x-field name="contact_email" label="Email for enquiries" type="email" :value="$private?->contact_email ?? auth()->user()->email" autocomplete="email" required />
                    <x-field name="contact_phone" label="Phone (optional)" type="tel" :value="$private?->contact_phone" autocomplete="tel" />
                    @unless ($isAffiliate)
                        @php
                            $showPhone = (string) old('show_phone', $private?->show_phone ? '1' : '0');
                        @endphp
                        <fieldset class="mb-4" aria-describedby="show-phone-hint">
                            <legend class="mb-1 font-medium">Show your phone number on your listing page?</legend>
                            <p id="show-phone-hint" class="mb-2 text-sm text-slate-600">Paid plans can show your phone number to visitors. It is never shown on the Free plan. If you choose hidden, we keep it private.</p>
                            <div class="flex min-h-11 items-center gap-2"><input id="show-phone-yes" type="radio" name="show_phone" value="1" class="h-5 w-5" @checked($showPhone === '1')><label for="show-phone-yes">Yes, show it publicly</label></div>
                            <div class="flex min-h-11 items-center gap-2"><input id="show-phone-no" type="radio" name="show_phone" value="0" class="h-5 w-5" @checked($showPhone !== '1')><label for="show-phone-no">No, keep it hidden</label></div>
                        </fieldset>
                    @endunless
                    <x-field name="website_url" label="Your website (optional)" type="url" :value="$private?->website_url" hint="Must start with https://" />

                    <x-button>Save and continue</x-button>
                </form>
                @break

            @case('photo')
                <form method="POST" action="{{ route('account.listings.photo', $listing) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <h2 class="mb-2 text-xl font-semibold">{{ $isAffiliate ? 'Photo' : 'Main photo' }}</h2>
                    <p class="mb-4">{{ $isAffiliate ? 'Each partner listing has one photo.' : 'Your main photo shows first, on cards and at the top of your listing.' }} Choose your best one: bright, level and showing the home or the view.</p>

                    @if ($listing->imageUrl())
                        <img src="{{ $listing->imageUrl(800) }}" alt="{{ $listing->image_alt }}" class="mb-4 w-full max-w-md rounded" width="800" height="600">
                    @elseif ($listing->exists && ! $listing->image_path && session('status'))
                        <p class="mb-4 text-sm">If you have just uploaded a photo, it may take a minute to appear.</p>
                    @endif

                    <div class="mb-4">
                        <label for="photo" class="mb-1 block font-medium">{{ $listing->image_path ? 'Replace the photo (optional)' : 'Photo' }}</label>
                        <p id="photo-hint" class="mb-1 text-sm text-slate-600">JPG, PNG or WebP, at least 800 pixels wide, up to 10 MB.</p>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-hint" class="block min-h-11 w-full">
                    </div>

                    <x-field name="image_alt" label="Describe the photo" :value="$listing->image_alt" maxlength="160"
                             hint="This is read out to people who cannot see the photo. For example: White villa with a pool and sea views." required />

                    <div class="mb-6 flex items-start gap-2">
                        <input id="photo_permission" name="photo_permission" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0">
                        <label for="photo_permission">I took this photo or have permission to use it, and I allow Just Island Holidays to show it on this listing.</label>
                    </div>

                    <x-button>Save and continue</x-button>
                </form>

                {{-- More photos (owners and agents): up to the plan's limit, counting the main photo. --}}
                @if (! $isAffiliate && $listing->exists)
                    @php
                        $photoLimit = $listing->photoLimit();
                        $extras = $listing->photos;
                        $room = max(0, $photoLimit - 1 - $extras->count());
                    @endphp
                    <section class="mt-8 border-t border-slate-200 pt-6" aria-labelledby="more-photos-heading">
                        <h2 id="more-photos-heading" class="mb-2 text-xl font-semibold">More photos</h2>
                        <p class="mb-4">Your plan allows {{ $photoLimit }} photos in all, including your main photo. You have {{ 1 + $extras->count() }} {{ $room > 0 ? "and can add {$room} more" : 'and have used them all' }}.</p>

                        @if ($extras->isNotEmpty())
                            <ul class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($extras as $extra)
                                    <li class="rounded border border-slate-200 bg-white p-2" data-extra-photo>
                                        @if ($extra->url(400))
                                            <img src="{{ $extra->url(400) }}" alt="{{ $extra->alt }}" width="400" height="300" class="aspect-[4/3] w-full rounded object-cover">
                                        @else
                                            <p class="flex aspect-[4/3] items-center justify-center rounded bg-slate-100 p-2 text-center text-sm">Being prepared, it will appear in a minute.</p>
                                        @endif
                                        <p class="mt-1 text-sm">{{ $extra->alt }}</p>
                                        <form method="POST" action="{{ route('account.listings.photos.destroy', [$listing, $extra->id]) }}" class="mt-1" onsubmit="return confirm('Remove this photo?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="min-h-11 text-sm text-red-800 underline">Remove<span class="sr-only"> photo: {{ $extra->alt }}</span></button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($room > 0 && $listing->image_path)
                            <form method="POST" action="{{ route('account.listings.photos.store', $listing) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-4">
                                    <label for="extra_photo" class="mb-1 block font-medium">Add a photo</label>
                                    <p id="extra-photo-hint" class="mb-1 text-sm text-slate-600">JPG, PNG or WebP, at least 800 pixels wide, up to 10 MB.</p>
                                    <input id="extra_photo" name="extra_photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="extra-photo-hint" class="block min-h-11 w-full" required>
                                    @error('extra_photo')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <x-field name="extra_alt" label="Describe the photo" maxlength="160" hint="For example: Shaded terrace with a dining table." required />
                                <div class="mb-4 flex items-start gap-2">
                                    <input id="extra_permission" name="extra_permission" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0" required>
                                    <label for="extra_permission">I took this photo or have permission to use it, and I allow Just Island Holidays to show it on this listing.</label>
                                </div>
                                @error('extra_permission')<p class="mb-4 text-sm text-red-700">{{ $message }}</p>@enderror
                                <x-button>Add photo</x-button>
                            </form>
                        @elseif (! $listing->image_path)
                            <p class="text-sm">Save your main photo first, then you can add more.</p>
                        @endif
                    </section>
                @endif
                @break

            @case('link')
                <form method="POST" action="{{ route('account.listings.link', $listing) }}">
                    @csrf @method('PUT')
                    <h2 class="mb-2 text-xl font-semibold">Booking link</h2>
                    @if ($isAffiliate)
                        <p class="mb-4">The page on your booking site for this property. Visitors reach it through the Check availability button.</p>
                    @else
                        <p class="mb-4">Optional. If guests can check dates and book on your own website or a booking site, add the page here and visitors will see a Check availability button.</p>
                        <p class="mb-4 rounded border border-slate-300 bg-white p-3 text-sm"><strong>You look after this link.</strong> We do not check whether it works. Please make sure it opens the right property, and update it here if the page moves.</p>
                    @endif

                    <x-field name="deeplink_url" label="Booking page address" type="url" :value="$listing->deeplink_url" hint="Must start with https://" :required="$isAffiliate" />

                    @if ($isAffiliate)
                        <x-field name="deeplink_subid_param" label="Click id parameter (optional)" :value="$listing->deeplink_subid_param"
                                 hint="If your programme reports a sub-id back to us, the name of that parameter, for example subid. We add our click id to it." />
                    @endif

                    {{-- Only affiliate links are checked, so only they go back for review. --}}
                    @if ($isAffiliate && $listing->status->value === 'live')
                        <p class="mb-4 rounded bg-amber-50 p-3 text-sm">Changing the link sends your listing back for a quick check before it is live again.</p>
                    @endif

                    <x-button>Save and continue</x-button>
                </form>
                @break

            @case('licence')
                <form method="POST" action="{{ route('account.listings.licence', $listing) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <h2 class="mb-2 text-xl font-semibold">Tourist licence</h2>
                    <p class="mb-4">Holiday lets in the Balearic Islands need a tourist licence. We check your number in the official register before your listing goes live, and show it on your listing.</p>

                    <x-field name="registration_number" label="Balearic tourist licence number" :value="$licence?->registration_number" hint="For example ETV/1234." />
                    <x-field name="national_registry_no" label="National registry number (if you have one)" :value="$licence?->national_registry_no" />

                    <div class="mb-4">
                        <label for="document" class="mb-1 block font-medium">Licence document (optional)</label>
                        <p id="document-hint" class="mb-1 text-sm text-slate-600">PDF, JPG or PNG, up to 5 MB. Only our team can see it.</p>
                        <input id="document" name="document" type="file" accept=".pdf,image/jpeg,image/png" aria-describedby="document-hint" class="block min-h-11 w-full">
                        @if ($licence?->document_path)
                            <p class="mt-2 text-sm"><a href="{{ route('account.listings.licence-document', $listing) }}" class="underline">Download the document you uploaded</a></p>
                        @endif
                    </div>

                    @if ($licence)
                        <p class="mb-4">Check status: <strong>{{ ['not_provided' => 'Not added', 'pending' => 'Waiting for our check', 'verified' => 'Verified', 'rejected' => 'Not accepted'][$licence->status] }}</strong>
                            @if ($licence->status === 'rejected' && $licence->notes) – {{ $licence->notes }} @endif</p>
                    @endif

                    <div class="mb-6 flex items-start gap-2">
                        <input id="responsibility" name="responsibility" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0">
                        <label for="responsibility">I confirm I hold a valid tourist licence for this property and I am responsible for keeping it valid.</label>
                    </div>

                    <x-button>Save and continue</x-button>
                </form>
                @break

            @case('review')
                <h2 class="mb-4 text-xl font-semibold">Review and send</h2>
                @if ($missing !== [])
                    <p class="mb-2">Before you can send your listing for review:</p>
                    <ul class="mb-6 list-disc space-y-1 pl-5">
                        @foreach ($missing as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @elseif ($listing->status->value === 'draft')
                    <p class="mb-6">Everything is in place. When you send your listing, our team checks it{{ $isAffiliate ? '' : ' and your licence number' }}, usually within 48 hours. We will email you when it is live.</p>
                @endif

                @if ($listing->status->value === 'draft')
                    <form method="POST" action="{{ route('account.listings.status', [$listing, 'submit']) }}">
                        @csrf
                        <x-button :disabled="$missing !== []">Send for review</x-button>
                    </form>
                @elseif ($listing->status->value === 'rejected')
                    <p class="mb-4">This listing was not approved.@if ($listing->rejection_reason) Reason: {{ $listing->rejection_reason }}@endif</p>
                    <form method="POST" action="{{ route('account.listings.status', [$listing, 'reopen']) }}">
                        @csrf
                        <x-button>Edit and send again</x-button>
                    </form>
                @else
                    @if ($isAffiliate)
                        <p>Status: <strong>{{ $listing->status->label() }}</strong>. Changes to your text and photo show straight away. Changes to the booking link are checked first.</p>
                    @else
                        <p>Status: <strong>{{ $listing->status->label() }}</strong>. Changes to your text, photo and booking link show straight away. Changes to the licence number are checked first.</p>
                    @endif
                @endif
                @break
        @endswitch
        </fieldset>
    </div>
@endsection
