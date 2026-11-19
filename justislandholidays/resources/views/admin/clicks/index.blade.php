@extends('layouts.admin', ['title' => 'Click reports'])

@php
    $field = 'min-h-11 rounded border border-slate-400 bg-white px-2';
    $uniques = (int) ($summary->uniques ?? 0);
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">Click reports</h1>
    <p class="mt-1">Check availability clicks from {{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}. {{ $includeBots ? 'Bots are included.' : 'Bots are left out.' }}</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter clicks">
        <div><label for="from" class="block text-sm font-medium">From</label><input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="{{ $field }}"></div>
        <div><label for="to" class="block text-sm font-medium">To</label><input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="{{ $field }}"></div>
        <div><label for="island" class="block text-sm font-medium">Island</label><select id="island" name="island" class="{{ $field }}"><option value="">All</option>@foreach ($islands as $i)<option value="{{ $i->id }}" @selected((int) request('island') === $i->id)>{{ $i->translate('name', 'en') }}</option>@endforeach</select></div>
        <div><label for="affiliate" class="block text-sm font-medium">Lister</label><select id="affiliate" name="affiliate" class="{{ $field }}"><option value="">All</option>@foreach ($affiliates as $a)<option value="{{ $a->id }}" @selected((int) request('affiliate') === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
        <div><label for="source" class="block text-sm font-medium">From page</label><select id="source" name="source" class="{{ $field }}"><option value="">Any</option>@foreach (['home', 'island', 'area', 'search', 'listing', 'other'] as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div><label for="device" class="block text-sm font-medium">Device</label><select id="device" name="device" class="{{ $field }}"><option value="">Any</option>@foreach (['desktop', 'mobile', 'tablet', 'unknown'] as $d)<option value="{{ $d }}" @selected(request('device') === $d)>{{ ucfirst($d) }}</option>@endforeach</select></div>
        <div><label for="group" class="block text-sm font-medium">Group by</label><select id="group" name="group" class="{{ $field }}">@foreach ($groups as $k => $l)<option value="{{ $k }}" @selected($group === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="period" class="block text-sm font-medium">Totals by</label><select id="period" name="period" class="{{ $field }}">@foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $k => $l)<option value="{{ $k }}" @selected($period === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="flex min-h-11 items-center gap-2"><input id="bots" name="bots" type="checkbox" value="1" class="h-5 w-5" @checked($includeBots)><label for="bots">Include bots</label></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Show</button>
    </form>

    <ul class="mt-4 grid gap-4 sm:grid-cols-2">
        <li class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">Clicks</p><p class="text-3xl font-semibold">{{ (int) ($summary->clicks ?? 0) }}</p></li>
        <li class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">Different visitors (unique clicks)</p><p class="text-3xl font-semibold">{{ $uniques }}</p></li>
    </ul>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="g-heading">
            <h2 id="g-heading" class="text-lg font-semibold">By {{ strtolower($groups[$group]) }}</h2>
            <table class="mt-2 w-full text-left text-sm">
                <caption class="sr-only">Clicks grouped by {{ strtolower($groups[$group]) }}</caption>
                <thead><tr class="border-b"><th scope="col" class="p-2">{{ $groups[$group] }}</th><th scope="col" class="p-2 text-right">Clicks</th><th scope="col" class="p-2 text-right">Unique</th></tr></thead>
                <tbody>@forelse ($grouped as $row)<tr class="border-b border-slate-100"><th scope="row" class="p-2 font-normal">{{ $row['label'] }}</th><td class="p-2 text-right">{{ $row['clicks'] }}</td><td class="p-2 text-right">{{ $row['uniques'] }}</td></tr>@empty<tr><td colspan="3" class="p-2">No clicks.</td></tr>@endforelse</tbody>
            </table>
        </section>
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="p-heading">
            <h2 id="p-heading" class="text-lg font-semibold">By {{ $period }}</h2>
            <table class="mt-2 w-full text-left text-sm">
                <caption class="sr-only">Clicks by {{ $period }}</caption>
                <thead><tr class="border-b"><th scope="col" class="p-2">{{ ucfirst($period) }}</th><th scope="col" class="p-2 text-right">Clicks</th><th scope="col" class="p-2 text-right">Unique</th></tr></thead>
                <tbody>@forelse ($byPeriod as $row)<tr class="border-b border-slate-100"><th scope="row" class="p-2 font-normal">{{ $row->period }}</th><td class="p-2 text-right">{{ $row->clicks }}</td><td class="p-2 text-right">{{ (int) $row->uniques }}</td></tr>@empty<tr><td colspan="3" class="p-2">No clicks.</td></tr>@endforelse</tbody>
            </table>
        </section>
    </div>

    <p class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.clicks.events', request()->query()) }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 bg-white px-4">See each click</a>
        <a href="{{ route('admin.clicks.export', request()->query()) }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Export CSV</a>
    </p>
@endsection
