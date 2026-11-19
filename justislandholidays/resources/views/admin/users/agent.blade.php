@extends('layouts.admin', ['title' => $user->name.': properties'])

@section('content')
    <p><a href="{{ route('admin.agents.index') }}" class="inline-flex min-h-11 items-center underline">Back to agents</a></p>
    <h1 class="text-2xl font-semibold">{{ $user->name }}</h1>
    <p class="mt-1">Agent{{ $user->agency ? ' at '.$user->agency->name : '' }} · {{ $user->email }} · {{ $plan->name }} plan · {{ $user->isDeactivated() ? 'Deactivated' : 'Active' }}</p>
    <p class="mt-2"><a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-11 items-center underline">Account details{{ auth()->user()->isSuperAdmin() ? ' and role' : '' }}</a></p>

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="props-heading">
        <h2 id="props-heading" class="text-lg font-semibold">Properties ({{ $listings->count() }})</h2>
        @unless (auth()->user()->isSuperAdmin())
            <p class="mt-1 text-sm">Only properties on your islands are shown.</p>
        @endunless

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ $user->name }}'s properties</caption>
                <thead class="border-b border-slate-300">
                    <tr><th scope="col" class="p-2">Property</th><th scope="col" class="p-2">Where</th><th scope="col" class="p-2">Type</th><th scope="col" class="p-2">Sleeps</th>
                        <th scope="col" class="p-2">Guide price</th><th scope="col" class="p-2">Status</th><th scope="col" class="p-2 text-right">Clicks, 30 days</th><th scope="col" class="p-2">On the site</th></tr>
                </thead>
                <tbody>
                    @forelse ($listings as $l)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="p-2"><a href="{{ route('admin.listings.show', $l) }}" class="font-medium underline">{{ $l->title('en') }}</a></td>
                            <td class="p-2">{{ $l->area->translate('name', 'en') }}, {{ $l->island->translate('name', 'en') }}</td>
                            <td class="p-2">{{ \App\Models\PropertyType::label($l->property_type, 'en') }}</td>
                            <td class="p-2">{{ $l->sleeps }}</td>
                            <td class="p-2">{{ $l->guidePrice() ? $l->guidePrice().' a week' : '–' }}</td>
                            <td class="p-2">{{ $l->status->label() }}</td>
                            <td class="p-2 text-right">{{ $l->clicks_30 }}</td>
                            <td class="p-2">
                                @if ($l->status->value === 'live' && $l->island->is_active)
                                    <a href="{{ $l->publicUrl('en') }}" class="inline-flex min-h-11 items-center underline">View<span class="sr-only"> {{ $l->title('en') }} on the site</span></a>
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-4">No properties{{ auth()->user()->isSuperAdmin() ? '' : ' on your islands' }} yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
