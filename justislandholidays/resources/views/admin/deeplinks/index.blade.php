@extends('layouts.admin', ['title' => 'Deeplink monitor'])

@section('content')
    <h1 class="text-2xl font-semibold">Deeplink monitor</h1>
    <p class="mt-1">Every listing with a Check availability link. Broken links come first, then the most clicked.</p>

    <nav aria-label="Filter by status" class="mt-4">
        <ul class="flex flex-wrap gap-2">
            @foreach (['' => 'All', 'broken' => 'Broken', 'unchecked' => 'Not checked', 'ok' => 'Working'] as $v => $l)
                <li><a href="{{ route('admin.deeplinks.index', array_filter(['status' => $v])) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-slate-900 bg-white font-semibold' => request('status', '') === $v, 'border-slate-300' => request('status', '') !== $v])>{{ $l }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Listings with booking links</caption>
            <thead class="border-b border-slate-300">
                <tr><th scope="col" class="p-2">Listing</th><th scope="col" class="p-2">Goes to</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2">Last checked</th><th scope="col" class="p-2 text-right">Clicks, 7 days</th><th scope="col" class="p-2">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($listings as $listing)
                    @php
                        $allowed = \App\Listings\DeeplinkChecker::isAllowedHost($listing->deeplink_url);
                    @endphp
                    <tr @class(['border-b border-slate-200 align-top', 'bg-red-50' => $listing->deeplink_status === 'broken'])>
                        <td class="p-2"><a href="{{ route('admin.listings.show', $listing) }}" class="font-medium underline">{{ $listing->title('en') }}</a><br><span class="text-slate-600">{{ $listing->island->translate('name', 'en') }} · {{ $listing->status->label() }}</span></td>
                        <td class="p-2">{{ parse_url($listing->deeplink_url, PHP_URL_HOST) }}
                            @unless ($allowed)<span class="mt-1 block font-medium text-amber-800">Not on the allowed list</span>@endunless</td>
                        <td class="p-2">{{ ['unchecked' => 'Not checked', 'ok' => 'Working', 'broken' => 'Broken'][$listing->deeplink_status] }}</td>
                        <td class="p-2">{{ $listing->deeplink_checked_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="p-2 text-right">{{ $listing->clicks_7 }}</td>
                        <td class="space-y-2 p-2">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.listings.test-link', $listing) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">Test</a>
                                <form method="POST" action="{{ route('admin.deeplinks.recheck', $listing) }}">@csrf<button class="min-h-11 rounded border border-slate-400 px-3">Recheck now</button></form>
                                @if ($listing->deeplink_status !== 'broken')
                                    <form method="POST" action="{{ route('admin.deeplinks.switch-off', $listing) }}" onsubmit="return confirm('Switch this link off? The Check availability button disappears straight away.')">@csrf<button class="min-h-11 rounded border border-red-800 px-3 text-red-800">Switch off</button></form>
                                @endif
                            </div>
                            @if ($listing->status->value === 'live')
                                <x-admin.reason-action :action="route('admin.deeplinks.pause', $listing)" label="Pause listing" />
                            @endif
                            <details class="rounded border border-slate-300">
                                <summary class="flex min-h-11 cursor-pointer items-center px-3">Message the lister</summary>
                                <form method="POST" action="{{ route('admin.deeplinks.message', $listing) }}" class="space-y-2 p-3">
                                    @csrf
                                    <label for="msg-{{ $listing->id }}" class="block font-medium">Message</label>
                                    <textarea id="msg-{{ $listing->id }}" name="message" rows="3" maxlength="1000" required class="block w-full rounded border border-slate-400 px-2 py-1"></textarea>
                                    <button class="min-h-11 rounded bg-slate-900 px-3 text-white">Send</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4">No listings with booking links.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $listings->links() }}</div>

    @can('manage-staff')
        <section class="mt-8 max-w-2xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="hosts-heading">
            <h2 id="hosts-heading" class="text-lg font-semibold">Allowed booking sites</h2>
            <p class="mt-1 text-sm">Leave empty to allow any https site. Links to other sites are flagged above (they still work).</p>
            <form method="POST" action="{{ route('admin.deeplinks.hosts') }}" class="mt-3 space-y-2">
                @csrf
                <label for="hosts" class="block font-medium">Website addresses, one per line</label>
                <textarea id="hosts" name="hosts" rows="4" class="block w-full rounded border border-slate-400 px-3 py-2 font-mono">{{ implode("\n", $allowedHosts) }}</textarea>
                <x-button>Save</x-button>
            </form>
        </section>
    @endcan
@endsection
