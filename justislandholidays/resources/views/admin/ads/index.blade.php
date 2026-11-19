@extends('layouts.admin', ['title' => 'Affiliate Adverts'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Affiliate Adverts</h1>
        <a href="{{ route('admin.ads.create', array_filter(['island' => $chosen?->id])) }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add an advert</a>
    </div>
    <p class="mt-1 max-w-3xl">Each island page has four advert columns below its introduction. Every advert belongs to one island and one column, and links to the advertiser's website. A column with no running advert shows an "Advertise here" placeholder. If two adverts share a column, they take turns.</p>

    <nav aria-label="Filter by island" class="mt-4">
        <ul class="flex flex-wrap gap-2">
            <li><a href="{{ route('admin.ads.index') }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => ! $chosen, 'border-slate-300' => $chosen]) @if (! $chosen) aria-current="page" @endif>All islands</a></li>
            @foreach ($islands as $i)
                <li><a href="{{ route('admin.ads.index', ['island' => $i->id]) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => $chosen?->id === $i->id, 'border-slate-300' => $chosen?->id !== $i->id]) @if ($chosen?->id === $i->id) aria-current="page" @endif>{{ $i->translate('name', 'en') }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Adverts</caption>
            <thead class="border-b border-slate-300">
                <tr><th scope="col" class="p-2">Advert</th><th scope="col" class="p-2">Island</th><th scope="col" class="p-2">Column</th><th scope="col" class="p-2">Links to</th>
                    <th scope="col" class="p-2">Dates</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2 text-right">Clicks</th><th scope="col" class="p-2"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                @forelse ($ads as $ad)
                    @php
                        $running = \App\Ads\IslandAdverts::isRunning($ad);
                    @endphp
                    <tr class="border-b border-slate-200 align-top">
                        <td class="p-2">
                            <div class="flex items-start gap-3">
                                <img src="{{ \App\Ads\IslandAdverts::imageUrl($ad) }}" alt="{{ $ad->alt }}" width="160" height="40" class="h-10 w-40 rounded object-cover">
                                <span><span class="font-medium">{{ $ad->advertiser->name }}</span><br><span class="text-slate-600">{{ \Illuminate\Support\Str::limit($ad->alt, 60) }}</span></span>
                            </div>
                        </td>
                        <td class="p-2">{{ $ad->island->translate('name', 'en') }}</td>
                        <td class="p-2">{{ $positions[$ad->slot_id] }}</td>
                        <td class="p-2 break-all">{{ parse_url($ad->target_url, PHP_URL_HOST) }}</td>
                        <td class="p-2">{{ $ad->starts_at->format('j M Y') }} to {{ $ad->ends_at->format('j M Y') }}</td>
                        <td class="p-2">
                            @if ($running) <span class="font-medium text-green-800">Showing</span>
                            @elseif ($ad->status === 'paused') Paused
                            @elseif ($ad->starts_at > now()) Starts {{ $ad->starts_at->format('j M') }}
                            @else Ended
                            @endif
                        </td>
                        <td class="p-2 text-right">{{ (int) $ad->clicks }}</td>
                        <td class="p-2"><a href="{{ route('admin.ads.edit', $ad) }}" class="inline-flex min-h-11 items-center underline">Edit<span class="sr-only"> advert for {{ $ad->advertiser->name }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4">No adverts yet{{ $chosen ? ' for '.$chosen->translate('name', 'en') : '' }}. Every column shows the placeholder.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
