@extends('layouts.admin', ['title' => 'Review: '.$listing->title('en')])

@php
    $en = $listing->translationFor('en')[0];
    $admin = auth()->user();
    $status = $listing->status->value;
    $isAffiliate = $listing->owner->isAffiliate();
    $openRec = $recommendations->firstWhere('status', 'open');
    $input = 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3';
    $pets = $listing->pets_allowed === null ? 'not_stated' : ($listing->pets_allowed ? 'yes' : 'no');
@endphp

@push('head')
    @vite('resources/js/map.js')
@endpush

@section('content')
    <p><a href="{{ route('admin.listings.index') }}" class="inline-flex min-h-11 items-center underline">Back to listings</a></p>
    <h1 class="text-2xl font-semibold">{{ $listing->title('en') }}</h1>
    <p class="mt-1">{{ $listing->status->label() }} · {{ $listing->island->translate('name', 'en') }}, {{ $listing->area->translate('name', 'en') }} · listed by {{ $listing->owner->name }} ({{ $listing->owner->role->label() }}, {{ $listing->owner->email }})</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_24rem]">
        {{-- What visitors will see --}}
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="text-lg font-semibold">As visitors will see it</h2>
            <div class="mt-3 aspect-[4/3] max-w-xl overflow-hidden rounded bg-slate-100">
                @if ($listing->imageUrl())
                    <img src="{{ $listing->imageUrl(800) }}" alt="{{ $listing->image_alt }}" class="h-full w-full object-cover" width="800" height="600">
                @else
                    <p class="flex h-full items-center justify-center text-slate-600">No photo</p>
                @endif
            </div>
            <p class="mt-2 text-sm"><strong>Photo description:</strong> {{ $listing->image_alt ?: 'None' }}</p>
            <h3 class="mt-4 text-xl font-semibold">{{ $en?->title }}</h3>
            <p class="mt-2">{{ $en?->summary }}</p>
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div><dt class="text-slate-600">Type</dt><dd>{{ \App\Models\PropertyType::label($listing->property_type, 'en') }}</dd></div>
                <div><dt class="text-slate-600">Sleeps</dt><dd>{{ $listing->sleeps }}</dd></div>
                <div><dt class="text-slate-600">Bedrooms</dt><dd>{{ $listing->bedrooms }}</dd></div>
                <div><dt class="text-slate-600">Bathrooms</dt><dd>{{ $listing->bathrooms }}</dd></div>
                <div><dt class="text-slate-600">Private pool</dt><dd>{{ $listing->has_private_pool ? 'Yes' : 'No' }}</dd></div>
                <div><dt class="text-slate-600">Pets</dt><dd>{{ ['yes' => 'Allowed', 'no' => 'Not allowed', 'not_stated' => 'Not stated (hidden)'][$pets] }}</dd></div>
                <div><dt class="text-slate-600">Guide price</dt><dd>{{ $listing->guidePrice() ? $listing->guidePrice().' a week' : 'None' }}</dd></div>
                <div><dt class="text-slate-600">To the beach</dt><dd>{{ $listing->distance_to_beach_m !== null ? $listing->distance_to_beach_m.' m' : 'Not given' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm"><strong>Public map pin:</strong> {{ $listing->display_lat }}, {{ $listing->display_lng }} (about 300 m from the exact location)
                @if ($listing->private) · <strong>Exact:</strong> {{ $listing->private->lat }}, {{ $listing->private->lng }} @endif</p>
            {{-- Admins only: the dark dot is the exact location, which visitors never see. --}}
            <div data-map="area" data-lat="{{ $listing->display_lat }}" data-lng="{{ $listing->display_lng }}" data-radius="{{ config('site.map.area_radius_m') }}"
                 @if ($listing->private) data-exact-lat="{{ $listing->private->lat }}" data-exact-lng="{{ $listing->private->lng }}" @endif
                 data-tiles="{{ config('site.map.tile_url') }}" data-attribution="{{ config('site.map.attribution') }}"
                 role="region" aria-label="Map: the circle is what visitors see, the dark dot is the exact location"
                 class="mt-3 h-64 overflow-hidden rounded border border-slate-200 bg-slate-100"></div>
            <p class="mt-1 text-sm text-slate-700">Visitors see the circle only. The dark dot is the exact location (admins only).</p>
            @if ($listing->source_url)
                <p class="mt-1 text-sm"><strong>Imported from:</strong> <span class="break-all">{{ $listing->source_url }}</span> (admins only)</p>
            @endif
            @if ($status === 'live')
                <p class="mt-3"><a href="{{ $listing->publicUrl('en') }}" class="inline-flex min-h-11 items-center underline">Open the public page</a></p>
            @endif
        </section>

        {{-- Review panel --}}
        <aside class="space-y-4" aria-label="Review">
            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="check-heading">
                <h2 id="check-heading" class="text-lg font-semibold">Checklist</h2>
                @if ($missing === [])
                    <p class="mt-2 font-medium text-green-800">Everything is in place.</p>
                @else
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($missing as $m)<li>{{ $m }}</li>@endforeach</ul>
                @endif
                <p class="mt-3 text-sm">Also check: the text is in English, the details are complete, the island and resort are right and the guide price is sensible.</p>
            </section>

            @unless ($isAffiliate)
                <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="lic-heading">
                    <h2 id="lic-heading" class="text-lg font-semibold">Licence</h2>
                    @if ($listing->licence)
                        <p class="mt-2">Balearic: <span class="font-mono">{{ $listing->licence->registration_number ?: '–' }}</span><br>National: <span class="font-mono">{{ $listing->licence->national_registry_no ?: '–' }}</span></p>
                        <p class="mt-1">Status: <strong>{{ ['not_provided' => 'Not added', 'pending' => 'To check', 'verified' => 'Verified', 'rejected' => 'Not accepted'][$listing->licence->status] }}</strong>
                            @if ($listing->licence->verifier) by {{ $listing->licence->verifier->name }} on {{ $listing->licence->verified_at?->format('j M Y') }} @endif</p>
                        @if ($listing->licence->status === 'pending')<p class="mt-2"><a href="{{ route('admin.licences.index') }}" class="inline-flex min-h-11 items-center underline">Check it on the Licence checks page</a></p>@endif
                    @else
                        <p class="mt-2">No licence number added yet.</p>
                    @endif
                </section>
            @endunless

            <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="link-heading">
                <h2 id="link-heading" class="text-lg font-semibold">Check availability link</h2>
                @if ($listing->deeplink_url)
                    <p class="mt-2 break-all text-sm">{{ $listing->deeplink_url }}</p>
                    @if ($isAffiliate)
                        <p class="mt-1">Goes to <strong>{{ parse_url($listing->deeplink_url, PHP_URL_HOST) }}</strong> · {{ $listing->deeplinkStatusLabel() }}
                            @if ($listing->deeplink_checked_at) · checked {{ $listing->deeplink_checked_at->diffForHumans() }} @endif</p>
                        @unless ($hostAllowed)<p class="mt-1 font-medium text-amber-800">This website is not on the list of allowed booking sites.</p>@endunless
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('admin.listings.test-link', $listing) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Test link (opens in a new tab, no click recorded)</a>
                            <form method="POST" action="{{ route('admin.listings.check-link', $listing) }}">@csrf<button type="submit" class="min-h-11 rounded border border-slate-400 px-3">Recheck now</button></form>
                            <form method="POST" action="{{ route('admin.listings.check-link', $listing) }}">@csrf<input type="hidden" name="mode" value="manual_ok"><button type="submit" class="min-h-11 rounded border border-slate-400 px-3">I tested it: it opens the right property</button></form>
                        </div>
                    @else
                        <p class="mt-1">Goes to <strong>{{ parse_url($listing->deeplink_url, PHP_URL_HOST) }}</strong>.</p>
                        <p class="mt-1 text-sm">The {{ $listing->owner->role->label() === 'Agent' ? 'agent' : 'owner' }} looks after this link. We do not check whether it works, and it does not need checking before approval.</p>
                        <p class="mt-3"><a href="{{ route('admin.listings.test-link', $listing) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Open the link (new tab, no click recorded)</a></p>
                    @endif
                @else
                    <p class="mt-2">No link.</p>
                @endif
            </section>

            <section class="space-y-2 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="actions-heading">
                <h2 id="actions-heading" class="text-lg font-semibold">Decision</h2>
                @if ($status === 'pending_review')
                    @can('approve', $listing)
                        <form method="POST" action="{{ route('admin.listings.approve', $listing) }}">
                            @csrf
                            <button type="submit" @disabled($missing !== []) class="min-h-11 w-full rounded bg-green-800 px-4 font-medium text-white disabled:opacity-50">Approve and publish</button>
                        </form>
                    @endcan
                    @can('reject', $listing)
                        <x-admin.reason-action :action="route('admin.listings.reject', $listing)" label="Reject" confirm="Reject this listing? The lister is emailed the reason." />
                    @endcan
                    @can('recommendRejection', $listing)
                        @if ($openRec)
                            <p class="text-sm">You recommended rejection on {{ $openRec->created_at->format('j M') }}. A super admin will decide.</p>
                        @else
                            <x-admin.reason-action :action="route('admin.listings.recommend', $listing)" label="Recommend rejection" hint="A super admin reads this and decides." :danger="false" />
                        @endif
                    @endcan
                @endif
                @if ($status === 'live')
                    <x-admin.reason-action :action="route('admin.listings.pause', $listing)" label="Pause" confirm="Pause this listing? It disappears from the site." />
                @endif
                @if (in_array($status, ['live', 'paused']))
                    <x-admin.reason-action :action="route('admin.listings.archive', $listing)" label="Archive" confirm="Archive this listing? It is removed from the site for good." />
                @endif
            </section>

            @if ($openRec && $admin->isSuperAdmin())
                <section class="rounded-lg border-2 border-amber-400 bg-white p-5 shadow-sm" aria-labelledby="rec-heading">
                    <h2 id="rec-heading" class="text-lg font-semibold">Recommended for rejection</h2>
                    <p class="mt-2">{{ $openRec->recommender->name }}: “{{ $openRec->reason }}”</p>
                    <form method="POST" action="{{ route('admin.recommendations.decide', $openRec) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="decision_note" class="block font-medium">Note (optional; for a rejection it is added to the reason the lister sees)</label>
                        <textarea id="decision_note" name="decision_note" rows="2" maxlength="500" class="block w-full rounded border border-slate-400 px-3 py-2"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="decision" value="accept" class="min-h-11 rounded bg-red-800 px-4 font-medium text-white" onclick="return confirm('Reject the listing?')">Reject the listing</button>
                            <button type="submit" name="decision" value="decline" class="min-h-11 rounded border border-slate-400 px-4">Send back (keep the listing)</button>
                        </div>
                    </form>
                </section>
            @endif
        </aside>
    </div>

    @can('update', $listing)
        <details class="mt-6 rounded-lg bg-white shadow-sm">
            <summary class="flex min-h-11 cursor-pointer items-center px-5 font-semibold">Edit details</summary>
            <form method="POST" action="{{ route('admin.listings.update', $listing) }}" class="grid gap-4 p-5 md:grid-cols-2">
                @csrf @method('PUT')
                <div class="md:col-span-2"><x-field name="title" label="Title" :value="$en?->title" maxlength="160" required /></div>
                <div class="md:col-span-2"><label for="summary" class="mb-1 block font-medium">Short description</label><textarea id="summary" name="summary" rows="3" maxlength="500" class="block w-full rounded border border-slate-400 px-3 py-2" required>{{ old('summary', $en?->summary) }}</textarea></div>
                <div><label for="area_id" class="mb-1 block font-medium">Resort</label><select id="area_id" name="area_id" class="{{ $input }}">@foreach ($areas as $a)<option value="{{ $a->id }}" @selected($listing->area_id === $a->id)>{{ $a->translate('name', 'en') }}</option>@endforeach</select></div>
                <div><label for="property_type" class="mb-1 block font-medium">Type</label><select id="property_type" name="property_type" class="{{ $input }}">@foreach (\App\Models\PropertyType::allowedFor($listing->property_type) as $t)<option value="{{ $t }}" @selected($listing->property_type === $t)>{{ \App\Models\PropertyType::label($t, 'en') }}</option>@endforeach</select></div>
                <x-field name="bedrooms" label="Bedrooms" type="number" min="0" :value="$listing->bedrooms" />
                <x-field name="bathrooms" label="Bathrooms" type="number" min="0" :value="$listing->bathrooms" />
                <x-field name="sleeps" label="Sleeps" type="number" min="1" max="40" :value="$listing->sleeps" />
                <x-field name="price" label="Guide price per week" type="number" min="0" :value="$listing->min_price_week_cents !== null ? intdiv($listing->min_price_week_cents, 100) : null" />
                <div><label for="pets" class="mb-1 block font-medium">Pets</label><select id="pets" name="pets" class="{{ $input }}">@foreach (['yes' => 'Allowed', 'no' => 'Not allowed', 'not_stated' => 'Not stated'] as $v => $l)<option value="{{ $v }}" @selected($pets === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="flex min-h-11 items-center gap-2"><input type="hidden" name="has_private_pool" value="0"><input id="has_private_pool" type="checkbox" name="has_private_pool" value="1" class="h-5 w-5" @checked($listing->has_private_pool)><label for="has_private_pool">Private pool</label></div>
                <div class="md:col-span-2"><x-field name="image_alt" label="Photo description" :value="$listing->image_alt" maxlength="160" /></div>
                <div><x-button>Save changes</x-button></div>
            </form>
        </details>
    @endcan

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="history-heading">
        <h2 id="history-heading" class="text-lg font-semibold">History</h2>
        @if ($history->isEmpty())
            <p class="mt-2">No changes recorded yet.</p>
        @else
            <table class="mt-2 w-full text-left text-sm">
                <caption class="sr-only">Changes to this listing, newest first</caption>
                <thead><tr class="border-b"><th scope="col" class="p-2">When</th><th scope="col" class="p-2">Who</th><th scope="col" class="p-2">What</th><th scope="col" class="p-2">Detail</th></tr></thead>
                <tbody>
                    @foreach ($history as $entry)
                        <tr class="border-b border-slate-100">
                            <td class="p-2 whitespace-nowrap">{{ $entry->created_at->format('j M Y H:i') }}</td>
                            <td class="p-2">{{ $entry->actor?->name ?? 'System' }}</td>
                            <td class="p-2">{{ $entry->action }}</td>
                            <td class="p-2">
                                @if (isset($entry->detail['before']['status'], $entry->detail['after']['status'])){{ $entry->detail['before']['status'] }} → {{ $entry->detail['after']['status'] }}@endif
                                @if (! empty($entry->detail['reason'])) · {{ $entry->detail['reason'] }}@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
