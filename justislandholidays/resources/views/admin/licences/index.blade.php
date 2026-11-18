@extends('layouts.admin', ['title' => 'Licence checks'])

@section('content')
    <h1 class="text-2xl font-semibold">Licence checks</h1>
    <p class="mt-1 max-w-3xl">Look up each number in the official register of tourist accommodation for the Balearic Islands, then record what you found. Nothing is checked automatically. Affiliate listings do not appear here.</p>

    <p class="mt-3">
        @if ($registerUrl)
            <a href="{{ $registerUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Open the official register (new tab)</a>
        @else
            <span class="rounded bg-amber-50 p-2">The register's address has not been set yet.</span>
        @endif
    </p>

    @can('manage-staff')
        <details class="mt-3 max-w-xl rounded bg-white shadow-sm">
            <summary class="flex min-h-11 cursor-pointer items-center px-4">Change the register's address</summary>
            <form method="POST" action="{{ route('admin.licences.register') }}" class="space-y-2 p-4">
                @csrf
                <x-field name="url" label="Register address (https)" type="url" :value="$registerUrl" />
                <x-button>Save</x-button>
            </form>
        </details>
    @endcan

    <div class="mt-6 space-y-4">
        @forelse ($licences as $licence)
            @php
                $listing = $licence->listing;
            @endphp
            <article class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="lic-{{ $listing->id }}">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <h2 id="lic-{{ $listing->id }}" class="text-lg font-semibold"><a href="{{ route('admin.listings.show', $listing) }}" class="underline">{{ $listing->title('en') }}</a></h2>
                        <p class="text-sm">{{ $listing->island->translate('name', 'en') }} · {{ $listing->owner->name }} ({{ $listing->owner->email }}) · sent {{ $licence->submitted_at ? \Illuminate\Support\Carbon::parse($licence->submitted_at)->format('j M Y') : 'not yet' }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6">
                        <dt class="text-sm text-slate-600">Balearic registration</dt><dd class="font-mono text-lg">{{ $licence->registration_number ?: '–' }}</dd>
                        <dt class="text-sm text-slate-600">National registry</dt><dd class="font-mono text-lg">{{ $licence->national_registry_no ?: '–' }}</dd>
                    </dl>
                </div>
                @if ($licence->document_path)
                    <p class="mt-2"><a href="{{ route('admin.licences.document', $listing) }}" class="inline-flex min-h-11 items-center underline">Download the uploaded document</a></p>
                @endif

                <div class="mt-3 flex flex-wrap items-start gap-3">
                    <form method="POST" action="{{ route('admin.licences.verify', $listing) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div><label for="notes-{{ $listing->id }}" class="block text-sm font-medium">Note (optional)</label>
                            <input id="notes-{{ $listing->id }}" name="notes" type="text" maxlength="500" class="min-h-11 rounded border border-slate-400 px-2"></div>
                        <button type="submit" class="min-h-11 rounded bg-green-800 px-4 font-medium text-white">Verified: the number matches the register</button>
                    </form>
                    <x-admin.reason-action :action="route('admin.licences.reject', $listing)" field="notes" label="Not accepted" hint="Say what was wrong. This is emailed to the lister." />
                </div>
            </article>
        @empty
            <p class="rounded-lg bg-white p-5 shadow-sm">No licences are waiting to be checked.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $licences->links() }}</div>
@endsection
