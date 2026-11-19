@extends('layouts.admin', ['title' => $affiliate->name])

@section('content')
    <p><a href="{{ route('admin.affiliates.index') }}" class="inline-flex min-h-11 items-center underline">Back to affiliates</a></p>
    <h1 class="text-2xl font-semibold">{{ $affiliate->name }}</h1>
    <p class="mt-1">{{ $affiliate->email }} · {{ $affiliate->isDeactivated() ? 'Deactivated' : 'Active' }} · last sign-in {{ $affiliate->last_login_at?->format('j M Y') ?? 'never' }}</p>
    @php
        $brokenLinks = $listings->whereNotNull('deeplink_url')->where('deeplink_status', 'broken')->count();
    @endphp
    <p class="mt-3"><a href="{{ route('admin.affiliates.deeplinks', $affiliate) }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 bg-white px-4 font-medium">Deeplinks{{ $brokenLinks ? " ({$brokenLinks} broken)" : '' }}</a></p>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="l-heading">
            <h2 id="l-heading" class="text-lg font-semibold">Listings</h2>
            @php
                $periodLabel = $periods[$period];
                $totalClicks = $listings->sum(fn ($l) => (int) $l->clicks_daily + (int) $l->clicks_recent);
            @endphp
            {{-- Choose the period, then Show (no change on select, WCAG 3.2.2). --}}
            <form method="GET" action="{{ route('admin.affiliates.show', $affiliate) }}" class="mt-2 flex flex-wrap items-end gap-2">
                <div>
                    <label for="clicks-period" class="block text-sm font-medium">Clicks for</label>
                    <select id="clicks-period" name="clicks" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        @foreach ($periods as $value => $label)
                            <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Show</button>
            </form>
            <p class="mt-2 text-sm" role="status">{{ trans_choice(':count click|:count clicks', $totalClicks) }} in total, {{ strtolower($periodLabel) }}. Clicks from search engines and other robots are not counted.</p>
            <table class="mt-2 w-full text-left text-sm">
                <caption class="sr-only">Listings and clicks, {{ strtolower($periodLabel) }}</caption>
                <thead><tr class="border-b"><th scope="col" class="p-2">Listing</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2 text-right">Clicks, {{ strtolower($periodLabel) }}</th></tr></thead>
                <tbody>
                    @forelse ($listings as $l)
                        <tr class="border-b border-slate-100"><td class="p-2"><a href="{{ route('admin.listings.show', $l) }}" class="underline">{{ $l->title('en') }}</a></td><td class="p-2">{{ $l->status->label() }}</td><td class="p-2 text-right">{{ (int) $l->clicks_daily + (int) $l->clicks_recent }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="p-2">No listings yet. <a href="{{ route('admin.import.create') }}" class="underline">Add one from a web address</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="d-heading">
            <h2 id="d-heading" class="text-lg font-semibold">Details and private notes</h2>
            <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}" class="mt-3">
                @csrf @method('PUT')
                @include('admin.affiliates.fields')
                <x-button>Save</x-button>
            </form>
        </section>
    </div>

    @php
        $logoUrl = \App\Support\AffiliateLogo::url($affiliate);
    @endphp
    <section class="mt-6 max-w-3xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="logo-heading">
        <h2 id="logo-heading" class="text-lg font-semibold">Logo</h2>
        <p class="mt-1 text-sm">Shown in a banner {{ \App\Support\AffiliateLogo::HEIGHT }} pixels tall above the Check availability button on {{ $affiliate->name }}'s property pages. The whole logo is shown, fitted inside the banner. PNG, JPG or WebP, at least {{ \App\Support\AffiliateLogo::MIN_HEIGHT }} pixels tall (200 looks sharpest), up to 2 MB. A transparent PNG works best.</p>

        <div class="mt-3 flex h-[100px] max-w-sm items-center justify-center rounded border border-slate-200 bg-white p-2">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $affiliate->affiliate_logo_alt }}" class="max-h-full max-w-full object-contain">
            @else
                <img src="{{ \App\Support\AffiliateLogo::placeholderUrl() }}" alt="" class="max-h-full max-w-full object-contain">
            @endif
        </div>
        @unless ($logoUrl)
            <p class="mt-2 text-sm font-medium">Their property pages show this placeholder until you add a logo.</p>
        @endunless

        <form method="POST" action="{{ route('admin.affiliates.logo', $affiliate) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
            @csrf
            <label for="logo" class="block font-medium">{{ $logoUrl ? 'Replace the logo (optional)' : 'Logo file' }}</label>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="block min-h-11" @unless ($logoUrl) required @endunless
                   @error('logo') aria-invalid="true" aria-errormessage="logo-error" @enderror>
            @error('logo')<p id="logo-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
            <x-field name="logo_alt" label="Description (alt text)" :value="$affiliate->affiliate_logo_alt ?? $affiliate->name" hint="Usually the company name, for example Sunny Stays." required />
            <x-button>{{ $logoUrl ? 'Save logo' : 'Add logo' }}</x-button>
        </form>
        @if ($logoUrl)
            <form method="POST" action="{{ route('admin.affiliates.logo', $affiliate) }}" class="mt-2" onsubmit="return confirm('Remove this logo? Their property pages show the placeholder again.')">
                @csrf
                <input type="hidden" name="remove" value="1">
                <button type="submit" class="min-h-11 rounded border border-red-800 px-4 text-red-800">Remove logo</button>
            </form>
        @endif
    </section>

    <section class="mt-6 flex flex-wrap items-start gap-3" aria-label="Account">
        <form method="POST" action="{{ route('admin.affiliates.invite', $affiliate) }}">@csrf<button class="min-h-11 rounded border border-slate-400 bg-white px-4">Send a new invitation</button></form>
        @if ($affiliate->isDeactivated())
            <form method="POST" action="{{ route('admin.affiliates.reactivate', $affiliate) }}">@csrf<button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Reactivate</button></form>
        @else
            <x-admin.reason-action :action="route('admin.affiliates.deactivate', $affiliate)" label="Deactivate affiliate" confirm="Deactivate this affiliate? All their listings are paused." />
        @endif
    </section>
@endsection
