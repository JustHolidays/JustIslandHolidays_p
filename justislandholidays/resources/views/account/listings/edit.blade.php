@extends($asAdmin ? 'layouts.admin' : 'layouts.account', ['title' => $listing->exists ? ($asAdmin ? 'Edit property: '.$listing->title('en') : 'Edit listing') : 'Add a listing'])

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
    @if ($asAdmin)
        <div class="mt-4 rounded border border-slate-300 bg-white p-4" data-admin-editor>
            <p>You are editing this property for <strong>{{ $listing->owner?->name }}</strong> ({{ $listing->owner?->role->label() }}, {{ $listing->owner?->email }}). The steps below are the ones they see, so they speak to them as "you". Your changes are saved in the audit log under your name and go live straight away; the status stays as it is.</p>
            <p class="mt-2"><a href="{{ route('admin.listings.show', $listing) }}" class="inline-flex min-h-11 items-center underline">Back to the property's admin page</a></p>
        </div>
    @endif

    <nav aria-label="Steps" class="mt-6">
        <ol class="flex flex-wrap gap-2">
            @foreach ($steps as $key => $label)
                <li>
                    @if ($listing->exists)
                        <a href="{{ route($r.'edit', [$listing, $key]) }}"
                           @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand,#0f172a)] bg-[var(--colour-brand,#0f172a)] text-white' => $step === $key, 'border-slate-300 bg-white' => $step !== $key])
                           @if ($step === $key) aria-current="step" @endif>{{ $loop->iteration }}. {{ $label }}</a>
                    @else
                        <span @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand,#0f172a)] bg-[var(--colour-brand,#0f172a)] text-white' => $key === 'basics', 'border-slate-300 bg-white text-slate-500' => $key !== 'basics'])
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
                <form method="POST" action="{{ $listing->exists ? route($r.'basics', $listing) : route('account.listings.store') }}">
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
                        @if ($priceFromRates !== null)
                            <div class="mb-4">
                                <p class="mb-1 font-medium">From price per week</p>
                                <p class="flex min-h-11 items-center">{{ $listing->guidePrice() }}</p>
                                <p class="text-sm text-slate-700">Set from your <a href="{{ route($r.'edit', [$listing, 'rates']) }}" class="underline">Rates</a>: your lowest weekly rate still to come.</p>
                                <input type="hidden" name="price" value="{{ intdiv($priceFromRates, 100) }}">
                            </div>
                        @else
                            <x-field name="price" :label="$isAffiliate ? 'From price per week (optional)' : 'From price per week'" type="number" min="1" step="1" :hint="$listing->exists && $listing->hasRates() ? 'Your lowest weekly price, shown as From Price. Add weekly rates in the Rates step and this is set for you.' : 'Your lowest weekly price, shown as From Price. You confirm the final price with guests.'" :required="! $isAffiliate" :value="$listing->min_price_week_cents !== null ? intdiv($listing->min_price_week_cents, 100) : null" />
                        @endif
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
                <form method="POST" action="{{ route($r.'location', $listing) }}">
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
                <form method="POST" action="{{ route($r.'photo', $listing) }}" enctype="multipart/form-data">
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
                                        <form method="POST" action="{{ route($r.'photos.destroy', [$listing, $extra->id]) }}" class="mt-1" onsubmit="return confirm('Remove this photo?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="min-h-11 text-sm text-red-800 underline">Remove<span class="sr-only"> photo: {{ $extra->alt }}</span></button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($room > 0 && $listing->image_path)
                            <form method="POST" action="{{ route($r.'photos.store', $listing) }}" enctype="multipart/form-data">
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
                <form method="POST" action="{{ route($r.'link', $listing) }}">
                    @csrf @method('PUT')
                    <h2 class="mb-2 text-xl font-semibold">{{ $isAffiliate ? 'Booking link' : 'Link to your booking page, website or Facebook' }}</h2>
                    @if ($isAffiliate)
                        <p class="mb-4">The page on your booking site for this property. Visitors reach it through the Check availability button.</p>
                    @elseif (! $listing->onPaidPlan())
                        <p class="mb-4">On a paid plan you can add a link to your booking page, your own website or your Facebook page, shown as a button on your listing.</p>
                        @if ($listing->deeplink_url)
                            <p class="mb-4 rounded bg-amber-50 p-3 text-sm">Your link ({{ parse_url($listing->deeplink_url, PHP_URL_HOST) }}) is kept but not shown while you are on the Free Plan. It shows again when you choose a paid plan.</p>
                        @endif
                        @unless ($asAdmin)<p class="mb-6"><a href="{{ route('account.billing') }}" class="underline">See the paid plans</a></p>@endunless
                    @else
                        <p class="mb-4">Optional. Add a link and visitors will see a button on your listing. Choose what it opens, and the button says so.</p>
                        <p class="mb-4 rounded border border-slate-300 bg-white p-3 text-sm"><strong>You look after this link.</strong> We do not check whether it works. Please make sure it opens the right page, and update it here if the page moves.</p>
                    @endif

                    @if ($isAffiliate || $listing->onPaidPlan())
                        @unless ($isAffiliate)
                            @php($linkTypeValue = old('link_type', $listing->link_type ?? 'booking'))
                            <fieldset class="mb-4">
                                <legend class="mb-1 font-medium">What does the link open?</legend>
                                @foreach (['booking' => ['Our booking page', 'Check availability'], 'website' => ['Our website', 'Visit Our Website'], 'facebook' => ['Our Facebook page', 'Visit Our Facebook']] as $value => [$label, $button])
                                    <div class="flex min-h-11 items-center gap-2">
                                        <input id="link_type_{{ $value }}" name="link_type" type="radio" value="{{ $value }}" class="h-5 w-5" @checked($linkTypeValue === $value)>
                                        <label for="link_type_{{ $value }}">{{ $label }} <span class="text-slate-600">(button: {{ $button }})</span></label>
                                    </div>
                                @endforeach
                                @error('link_type')<p class="text-sm font-medium text-red-800">{{ $message }}</p>@enderror
                            </fieldset>
                        @endunless

                        <x-field name="deeplink_url" :label="$isAffiliate ? 'Booking page address' : 'Link address'" type="url" :value="$listing->deeplink_url" hint="Must start with https://" :required="$isAffiliate" />
                    @endif

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

            @case('calendar')
                <h2 class="mb-2 text-xl font-semibold">Booking calendar</h2>
                <p class="mb-6">Show guests which dates are free. Import your calendars from Airbnb, Booking.com, Vrbo, Google Calendar and others, and mark dates you take yourself. Changes show on your listing straight away.</p>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="changeover-heading">
                    <h3 id="changeover-heading" class="text-lg font-semibold">When can guests arrive?</h3>
                    @php($arrivalsValue = old('arrivals', $listing->changeover_day === null ? null : ($listing->changeover_day === 0 ? 'any' : 'fixed')))
                    @php($dayValue = (int) old('changeover_day', $listing->changeover_day ?: 6))
                    @if ($listing->changeover_day === null)
                        <p class="mt-1 text-sm font-medium text-amber-800">Not chosen yet.</p>
                    @endif
                    <form data-keep-scroll method="POST" action="{{ route($r.'calendar.changeover', $listing) }}" class="mt-3">
                        @csrf @method('PUT')
                        <fieldset>
                            <legend class="sr-only">When can guests arrive?</legend>
                            <div class="flex min-h-11 items-start gap-2">
                                <input id="arrivals_any" name="arrivals" type="radio" value="any" class="mt-1 h-5 w-5" @checked($arrivalsValue === 'any')>
                                <label for="arrivals_any">Any day of the week <span class="block text-sm text-slate-600">Every rate period in your Rates needs a nightly rate.</span></label>
                            </div>
                            <div class="flex min-h-11 flex-wrap items-center gap-2">
                                <input id="arrivals_fixed" name="arrivals" type="radio" value="fixed" class="h-5 w-5" @checked($arrivalsValue === 'fixed')>
                                <label for="arrivals_fixed">On a fixed changeover day:</label>
                                <label for="changeover_day" class="sr-only">Changeover day</label>
                                <select id="changeover_day" name="changeover_day" class="min-h-11 rounded border border-slate-400 bg-white px-3" onchange="document.getElementById('arrivals_fixed').checked = true">
                                    @foreach (\App\Models\Listing::CHANGEOVER_DAYS as $n => $name)
                                        <option value="{{ $n }}" @selected($dayValue === $n)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </fieldset>
                        @error('arrivals')<p class="mt-2 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
                        @error('changeover_day')<p class="mt-2 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
                        <p class="mt-2 text-sm text-slate-700">A changeover day shows above the calendar on your listing, for example "Changeover day: Saturday".</p>
                        <div class="mt-3"><x-button>Save</x-button></div>
                    </form>
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="feeds-heading">
                    <h3 id="feeds-heading" class="text-lg font-semibold">Imported calendars</h3>
                    <p class="mt-1 text-sm text-slate-700">Paste the calendar's iCal address (it ends in .ics, or starts with webcal://). In Airbnb it is under Calendar, Availability, Connect calendars, Export calendar. In Google Calendar it is under Settings, your calendar, Secret address in iCal format. We check them every night, and you can press Sync now. Only the dates are imported, never guest names.</p>

                    @if ($feeds->isNotEmpty())
                        <ul class="mt-4 divide-y divide-slate-200">
                            @foreach ($feeds as $feed)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3" data-feed="{{ $feed->id }}">
                                    <div>
                                        <p class="font-medium">{{ $feed->name }}</p>
                                        <p class="text-sm {{ $feed->last_status === 'failed' ? 'font-medium text-red-800' : 'text-slate-600' }}">
                                            @if ($feed->last_status === 'ok') {{ trans_choice(':count booked stay|:count booked stays', $feed->bookings_count) }} · synced {{ $feed->last_synced_at?->diffForHumans() }}
                                            @elseif ($feed->last_status === 'failed') Could not be read: {{ $feed->last_error }}
                                            @else Not synced yet
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <form data-keep-scroll method="POST" action="{{ route($r.'calendar.feeds.sync', [$listing, $feed->id]) }}">@csrf<button type="submit" class="min-h-11 rounded border border-slate-400 px-3">Sync now<span class="sr-only"> {{ $feed->name }}</span></button></form>
                                        <form data-keep-scroll method="POST" action="{{ route($r.'calendar.feeds.destroy', [$listing, $feed->id]) }}" onsubmit="return confirm('Remove this calendar and the dates it brought in?')">@csrf @method('DELETE')<button type="submit" class="min-h-11 rounded border border-red-800 px-3 text-red-800">Remove<span class="sr-only"> {{ $feed->name }}</span></button></form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form data-keep-scroll method="POST" action="{{ route($r.'calendar.feeds.store', $listing) }}" class="mt-4 grid gap-3 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
                        @csrf
                        <x-field name="feed_name" label="Name" hint="For example Airbnb." required />
                        <x-field name="feed_url" label="iCal address" hint="Starts with https:// or webcal://" required autocomplete="off" />
                        <div class="mb-4"><x-button>Add calendar</x-button></div>
                    </form>
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="own-heading">
                    <h3 id="own-heading" class="text-lg font-semibold">Dates you take yourself</h3>
                    <p class="mt-1 text-sm text-slate-700">Press a day on the calendar below to book or free the night from that day. To enter a whole stay, or to see and change the stays you have booked, use the Booked dates page.</p>
                    <p class="mt-3">
                        <a href="{{ route($r.'edit', [$listing, 'bookings']) }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-5 font-medium text-white no-underline hover:bg-slate-700">Booked dates ({{ $ownBookings->count() + $importedBookings->count() }} coming up)</a>
                    </p>
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="embed-heading">
                    <h3 id="embed-heading" class="text-lg font-semibold">Show this calendar on your own website</h3>
                    @if ($listing->status->value === 'live')
                        <p class="mt-1 text-sm text-slate-700">Copy this code into your website (an "embed" or "HTML" block). The calendar stays up to date by itself.</p>
                        <label for="embed-code" class="mt-3 block font-medium">iFrame code</label>
                        <textarea id="embed-code" rows="3" readonly class="mt-1 block w-full rounded border border-slate-400 bg-slate-50 px-3 py-2 font-mono text-sm" onfocus="this.select()">{{ \App\Http\Controllers\Public\CalendarEmbedController::snippet($listing) }}</textarea>
                        <label for="embed-url" class="mt-3 block font-medium">Or just the address</label>
                        <input id="embed-url" type="text" readonly value="{{ \App\Http\Controllers\Public\CalendarEmbedController::url($listing) }}" class="mt-1 block min-h-11 w-full rounded border border-slate-400 bg-slate-50 px-3 font-mono text-sm" onfocus="this.select()">
                        <p class="mt-2 text-sm"><a href="{{ \App\Http\Controllers\Public\CalendarEmbedController::url($listing) }}" target="_blank" rel="noopener" class="underline">Preview it (new tab)</a></p>
                    @else
                        <p class="mt-1 text-sm text-slate-700">Once your listing is live, you will find the code here to show this calendar on your own website.</p>
                    @endif
                </section>

                <section id="your-calendar" class="scroll-mt-4" aria-labelledby="cal-heading">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 id="cal-heading" class="text-lg font-semibold">Your calendar</h3>
                        <nav aria-label="Calendar months" class="flex gap-2">
                            @if ($offset > 0)<a data-keep-scroll href="{{ route($r.'edit', [$listing, 'calendar', 'months' => max(0, $offset - 3)]) }}#your-calendar" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Earlier months</a>@endif
                            @if ($offset < 33)<a data-keep-scroll href="{{ route($r.'edit', [$listing, 'calendar', 'months' => $offset + 3]) }}#your-calendar" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Later months</a>@endif
                        </nav>
                    </div>
                    <form data-keep-scroll id="calendar-toggle" method="POST" action="{{ route($r.'calendar.toggle', $listing) }}">@csrf</form>
                    @error('date')<p class="mb-3 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
                    @include('partials.calendar-months', ['months' => $months, 'toggleAction' => true])
                </section>
                @break

            @case('bookings')
                <h2 class="mb-2 text-xl font-semibold">Booked dates</h2>
                <p class="mb-6">The stays booked on your calendar from today on. Change or free the ones you marked yourself here; stays from your imported calendars change at their source. Changes show on your listing straight away. <a href="{{ route($r.'edit', [$listing, 'calendar']) }}" class="underline">Back to the calendar</a></p>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="add-stay-heading">
                    <h3 id="add-stay-heading" class="text-lg font-semibold">Mark a stay as booked</h3>
                    <p class="mt-1 text-sm text-slate-700">From the arrival day up to the departure morning. The departure day shows as a half day, because the home is free again from the afternoon.</p>
                    <form data-keep-scroll method="POST" action="{{ route($r.'calendar.bookings.store', $listing) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        @csrf
                        <x-field name="check_in" label="Arrival" type="date" :value="today()->toDateString()" required />
                        <x-field name="check_out" label="Departure" type="date" :value="today()->addDay()->toDateString()" required />
                        <div class="mb-4"><x-button>Mark as booked</x-button></div>
                    </form>
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="own-list-heading">
                    <h3 id="own-list-heading" class="text-lg font-semibold">Stays you marked</h3>
                    @if ($ownBookings->isEmpty())
                        <p class="mt-2 text-sm text-slate-700">None coming up.</p>
                    @else
                        <ul class="mt-2 divide-y divide-slate-200">
                            @foreach ($ownBookings as $own)
                                @php($bag = $errors->getBag('booking'.$own->id))
                                <li class="py-4" data-booking="{{ $own->id }}">
                                    <p class="font-medium">{{ $own->check_in->format('D j M Y') }} to {{ $own->check_out->format('D j M Y') }} <span class="font-normal text-slate-600">({{ trans_choice(':count night|:count nights', $own->check_in->diffInDays($own->check_out)) }})</span>@if ($own->enquiry) <span class="block text-sm font-normal">Guest: {{ $own->enquiry->guest_name }}, enquiry @can('view', $own->enquiry) <a href="{{ route($asAdmin ? 'admin.enquiries.show' : 'account.enquiries.show', $own->enquiry) }}" class="underline">{{ $own->enquiry->reference() }}</a> @else {{ $own->enquiry->reference() }} @endcan</span> @elseif ($own->guest_ref) <span class="block text-sm font-normal">Guest: {{ $own->guest_ref }}</span> @endif</p>
                                    <form data-keep-scroll method="POST" action="{{ route($r.'calendar.bookings.update', [$listing, $own->id]) }}" class="mt-2 grid gap-3 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                                        @csrf @method('PUT')
                                        <div>
                                            <label for="in-{{ $own->id }}" class="mb-1 block text-sm font-medium">Arrival</label>
                                            <input id="in-{{ $own->id }}" name="check_in" type="date" required value="{{ $bag->any() ? old('check_in') : $own->check_in->toDateString() }}" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-3" @if ($bag->has('check_in')) aria-invalid="true" aria-describedby="in-{{ $own->id }}-error" @endif>
                                            @if ($bag->has('check_in'))<p id="in-{{ $own->id }}-error" class="mt-1 text-sm font-medium text-red-800">{{ $bag->first('check_in') }}</p>@endif
                                        </div>
                                        <div>
                                            <label for="out-{{ $own->id }}" class="mb-1 block text-sm font-medium">Departure</label>
                                            <input id="out-{{ $own->id }}" name="check_out" type="date" required value="{{ $bag->any() ? old('check_out') : $own->check_out->toDateString() }}" class="block min-h-11 w-full rounded border border-slate-400 bg-white px-3" @if ($bag->has('check_out')) aria-invalid="true" aria-describedby="out-{{ $own->id }}-error" @endif>
                                            @if ($bag->has('check_out'))<p id="out-{{ $own->id }}-error" class="mt-1 text-sm font-medium text-red-800">{{ $bag->first('check_out') }}</p>@endif
                                        </div>
                                        <x-button>Save dates<span class="sr-only"> for {{ $own->check_in->format('j M Y') }}</span></x-button>
                                    </form>
                                    <form data-keep-scroll method="POST" action="{{ route($r.'calendar.bookings.destroy', [$listing, $own->id]) }}" class="mt-2" onsubmit="return confirm('Free these dates?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="min-h-11 text-red-800 underline">Free these dates<span class="sr-only"> ({{ $own->check_in->format('j M Y') }} to {{ $own->check_out->format('j M Y') }})</span></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="imported-heading">
                    <h3 id="imported-heading" class="text-lg font-semibold">Stays from your imported calendars</h3>
                    @if ($importedBookings->isEmpty())
                        <p class="mt-2 text-sm text-slate-700">None coming up.</p>
                    @else
                        <p class="mt-1 text-sm text-slate-700">To change these, change them where they came from, then press Sync now on the Calendar page.</p>
                        <ul class="mt-2 divide-y divide-slate-200">
                            @foreach ($importedBookings as $stay)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <span>{{ $stay->check_in->format('D j M Y') }} to {{ $stay->check_out->format('D j M Y') }} <span class="text-slate-600">({{ trans_choice(':count night|:count nights', $stay->check_in->diffInDays($stay->check_out)) }})</span></span>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm">From {{ $stay->feed?->name ?? 'an imported calendar' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
                @break

            @case('rates')
                @php($symbol = ($listing->currency ?? 'EUR') === 'GBP' ? '£' : '€')
                <h2 class="mb-2 text-xl font-semibold">Rates</h2>
                <p class="mb-2">Add your prices for each period of the year. Guests see them as a table on your listing. Each period needs at least one price; leave the others empty if you do not offer them.</p>
                <p class="mb-6">Your From price is set from this table: it is your lowest weekly rate still to come{{ $priceFromRates !== null ? ', now '.$listing->guidePrice() : '' }}. Prices are in {{ $symbol === '£' ? 'pounds' : 'euros' }}; you can change the currency in the Basics step.</p>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="rates-heading">
                    <h3 id="rates-heading" class="text-lg font-semibold">Your rate periods</h3>
                    @if ($rates->isEmpty())
                        <p class="mt-2 text-sm text-slate-700">No rates yet. Add your first period below.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full min-w-[36rem] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-300">
                                        <th scope="col" class="py-2 pr-3">Dates</th>
                                        <th scope="col" class="py-2 pr-3">Per night</th>
                                        <th scope="col" class="py-2 pr-3">Per week</th>
                                        <th scope="col" class="py-2 pr-3">14 nights</th>
                                        <th scope="col" class="py-2"><span class="sr-only">Remove</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rates as $rate)
                                        @php($ended = $rate->end_date->lt(today()))
                                        <tr @class(['border-b border-slate-200', 'text-slate-500' => $ended]) data-rate="{{ $rate->id }}">
                                            <td class="py-2 pr-3">{{ $rate->start_date->format('j M Y') }} to {{ $rate->end_date->format('j M Y') }}@if ($ended) <span class="block text-xs">Ended, not shown to guests</span>@endif</td>
                                            <td class="py-2 pr-3">{{ \App\Listings\Rates::money($rate->nightly_cents, $listing->currency) }}</td>
                                            <td class="py-2 pr-3">{{ \App\Listings\Rates::money($rate->weekly_cents, $listing->currency) }}</td>
                                            <td class="py-2 pr-3">{{ \App\Listings\Rates::money($rate->fortnight_cents, $listing->currency) }}</td>
                                            <td class="py-2 text-right">
                                                <form data-keep-scroll method="POST" action="{{ route($r.'rates.destroy', [$listing, $rate->id]) }}">@csrf @method('DELETE')<button type="submit" class="min-h-11 text-red-800 underline">Remove<span class="sr-only"> {{ $rate->start_date->format('j M Y') }} to {{ $rate->end_date->format('j M Y') }}</span></button></form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="add-rate-heading">
                    <h3 id="add-rate-heading" class="text-lg font-semibold">Add a period</h3>
                    <p class="mt-1 text-sm text-slate-700">Both dates are included. Periods cannot overlap; to change one, remove it and add it again.</p>
                    @if ($listing->arrivesAnyDay())
                        <p class="mt-1 text-sm font-semibold">Guests can arrive any day, so each period needs a nightly rate.</p>
                    @endif
                    <form data-keep-scroll method="POST" action="{{ route($r.'rates.store', $listing) }}" class="mt-3">
                        @csrf
                        <div class="grid gap-x-4 sm:grid-cols-2">
                            <x-field name="start_date" label="Start date" type="date" required />
                            <x-field name="end_date" label="End date" type="date" required />
                        </div>
                        <div class="grid gap-x-4 sm:grid-cols-3">
                            <x-field name="nightly" :label="'Per night ('.$symbol.')'" type="number" min="1" step="1" :required="$listing->arrivesAnyDay()" />
                            <x-field name="weekly" :label="'Per week ('.$symbol.')'" type="number" min="1" step="1" />
                            <x-field name="fortnight" :label="'14 nights ('.$symbol.')'" type="number" min="1" step="1" />
                        </div>
                        <x-button>Add period</x-button>
                    </form>
                </section>
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
