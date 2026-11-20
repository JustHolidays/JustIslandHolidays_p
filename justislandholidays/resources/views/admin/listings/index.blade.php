@extends('layouts.admin', ['title' => 'Listing Search'])

@php
    $select = 'min-h-11 rounded border border-slate-400 bg-white px-2';
    $statuses = ['pending_review' => 'Waiting for review', 'live' => 'Live', 'paused' => 'Paused', 'draft' => 'Draft', 'rejected' => 'Rejected', 'archived' => 'Archived', 'all' => 'All'];
    $licenceLabels = ['not_provided' => 'Not added', 'pending' => 'To check', 'verified' => 'Verified', 'rejected' => 'Not accepted'];
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Listing Search</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.listings.create') }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add a property for an owner or agent</a>
            <a href="{{ route('admin.import.create') }}" class="inline-flex min-h-11 items-center rounded border border-slate-900 bg-white px-4 font-medium">Add an affiliate listing from a web address</a>
            @can('manage-listing-csv')
                <a href="{{ route('admin.listings.csv') }}" class="inline-flex min-h-11 items-center rounded border border-slate-900 bg-white px-4 font-medium">Import and export (CSV)</a>
            @endcan
        </div>
    </div>

    @can('decide-recommendations')
        <nav aria-label="Listing views" class="mt-4">
            <ul class="flex gap-2">
                <li><a href="{{ route('admin.listings.index') }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => $tab === 'all', 'border-slate-300' => $tab !== 'all']) @if ($tab === 'all') aria-current="page" @endif>All listings</a></li>
                <li><a href="{{ route('admin.listings.index', ['tab' => 'recommended']) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => $tab === 'recommended', 'border-slate-300' => $tab !== 'recommended']) @if ($tab === 'recommended') aria-current="page" @endif>Recommended for rejection</a></li>
            </ul>
        </nav>
    @endcan

    <form method="GET" action="{{ route('admin.listings.index') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter listings">
        @if ($tab === 'recommended')<input type="hidden" name="tab" value="recommended">@endif
        <div><label for="f-q" class="block text-sm font-medium">Title or lister email</label><input id="f-q" name="q" type="search" value="{{ request('q') }}" class="{{ $select }}"></div>
        <div><label for="f-status" class="block text-sm font-medium">Status</label>
            <select id="f-status" name="status" class="{{ $select }}">@foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected($status === $v)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="f-area" class="block text-sm font-medium">Resort</label>
            <select id="f-area" name="area" class="{{ $select }}"><option value="">Any</option>@foreach ($areas as $a)<option value="{{ $a->id }}" @selected((int) request('area') === $a->id)>{{ $a->translate('name', 'en') }}</option>@endforeach</select></div>
        <div><label for="f-role" class="block text-sm font-medium">Lister</label>
            <select id="f-role" name="role" class="{{ $select }}"><option value="">Any</option>@foreach (['owner' => 'Owner', 'agent' => 'Agent', 'affiliate' => 'Affiliate'] as $v => $l)<option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="f-type" class="block text-sm font-medium">Type</label>
            <select id="f-type" name="type" class="{{ $select }}"><option value="">Any</option>@foreach (\App\Models\PropertyType::cached()->keys() as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ \App\Models\PropertyType::label($t, 'en') }}</option>@endforeach</select></div>
        <div><label for="f-licence" class="block text-sm font-medium">Licence</label>
            <select id="f-licence" name="licence" class="{{ $select }}"><option value="">Any</option>@foreach ($licenceLabels as $v => $l)<option value="{{ $v }}" @selected(request('licence') === $v)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="f-deeplink" class="block text-sm font-medium">Booking link</label>
            <select id="f-deeplink" name="deeplink" class="{{ $select }}"><option value="">Any</option>@foreach (['broken' => 'Affiliate link broken', 'unchecked' => 'Affiliate link not checked', 'ok' => 'Affiliate link working', 'lister' => 'Owner or agent link', 'none' => 'No link'] as $v => $l)<option value="{{ $v }}" @selected(request('deeplink') === $v)>{{ $l }}</option>@endforeach</select></div>
        <button type="submit" class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Filter</button>
    </form>

    <form method="POST" action="{{ route('admin.listings.bulk') }}" class="mt-4" id="bulk-form">
        @csrf
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">Listings. {{ $listings->total() }} found.</caption>
                <thead class="border-b border-slate-300">
                    <tr>
                        <th scope="col" class="p-2"><span class="sr-only">Select</span></th>
                        <th scope="col" class="p-2 text-left">Photo</th>
                        <th scope="col" class="p-2 text-left">Title</th>
                        <th scope="col" class="p-2 text-left">Island and resort</th>
                        <th scope="col" class="p-2 text-left">Lister</th>
                        <x-admin.sort key="type" label="Type" />
                        <x-admin.sort key="submitted" label="Sent for review" default="submitted" />
                        <th scope="col" class="p-2 text-left">Licence</th>
                        <th scope="col" class="p-2 text-left">Booking link</th>
                        <th scope="col" class="p-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listings as $listing)
                        @php
                            $submitted = $listing->submitted_at ? \Illuminate\Support\Carbon::parse($listing->submitted_at) : null;
                            $late = $listing->status->value === 'pending_review' && $submitted && $submitted->lt($target);
                        @endphp
                        <tr @class(['border-b border-slate-200', 'bg-red-50' => $late])>
                            <td class="p-2"><label class="flex min-h-11 min-w-11 items-center justify-center"><span class="sr-only">Select {{ $listing->title('en') }}</span><input type="checkbox" name="ids[]" value="{{ $listing->id }}" class="h-5 w-5"></label></td>
                            <td class="p-2">
                                @if ($listing->imageUrl())<img src="{{ $listing->imageUrl(400) }}" alt="{{ $listing->image_alt }}" width="64" height="48" class="h-12 w-16 rounded object-cover">@else<span class="text-xs text-slate-500">No photo</span>@endif
                            </td>
                            <td class="p-2"><a href="{{ route('admin.listings.show', $listing) }}" class="font-medium underline">{{ $listing->title('en') }}</a></td>
                            <td class="p-2">{{ $listing->island->translate('name', 'en') }}, {{ $listing->area->translate('name', 'en') }}</td>
                            <td class="p-2">{{ $listing->owner->name }}<br><span class="text-slate-600">{{ $listing->owner->role->label() }}</span></td>
                            <td class="p-2">{{ \App\Models\PropertyType::label($listing->property_type, 'en') }}</td>
                            <td class="p-2">{{ $submitted?->format('j M Y H:i') ?? '–' }} @if ($late)<span class="block font-semibold text-red-800">Over {{ config('site.review_target_hours') }} hours</span>@endif</td>
                            <td class="p-2">{{ $listing->owner->isAffiliate() ? 'Not needed' : ($listing->licence ? $licenceLabels[$listing->licence->status] : 'Not added') }}</td>
                            <td class="p-2">{{ $listing->deeplinkStatusLabel() }}</td>
                            <td class="p-2">{{ $listing->status->label() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-4">No listings match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <fieldset class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
            <legend class="sr-only">Act on the ticked listings</legend>
            <div><label for="bulk-action" class="block text-sm font-medium">With the ticked listings</label>
                <select id="bulk-action" name="action" class="{{ $select }}"><option value="pause">Pause</option><option value="archive">Archive</option></select></div>
            <div class="min-w-64 flex-1"><label for="bulk-reason" class="block text-sm font-medium">Reason (emailed to each lister)</label>
                <input id="bulk-reason" name="reason" type="text" maxlength="500" required class="{{ $select }} w-full"></div>
            <button type="submit" class="min-h-11 rounded bg-red-800 px-4 font-medium text-white" onclick="return confirm('Apply this to every ticked listing?')">Apply</button>
        </fieldset>
    </form>

    <div class="mt-4">{{ $listings->links() }}</div>
@endsection
