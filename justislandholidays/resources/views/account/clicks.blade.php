@extends('layouts.account', ['title' => 'Views and clicks'])

@section('content')
    <h1 class="text-2xl font-semibold">Views and clicks</h1>
    <p class="mt-1">How many times each of your property pages was viewed, and how often visitors pressed its button to your booking page, website or Facebook. Known bots are left out. A visitor counts once per property in any 30 minutes; you and our team looking at your pages are not counted.</p>

    <nav aria-label="Period" class="mt-4">
        <ul class="flex flex-wrap gap-2">
            @foreach ($periods as $key => $label)
                <li><a href="{{ route('account.clicks', ['period' => $key]) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] font-semibold' => $period === $key, 'border-slate-300' => $period !== $key]) @if ($period === $key) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    @if ($rows->isEmpty())
        <p class="mt-6">You have no properties yet, so there is nothing to show.</p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="w-full border-collapse bg-white text-left">
                <caption class="sr-only">Views and clicks per property, {{ strtolower($periods[$period]) }}</caption>
                <thead>
                    <tr class="border-b border-slate-300">
                        <th scope="col" class="p-3">Property</th>
                        <th scope="col" class="p-3 text-right">Page views</th>
                        <th scope="col" class="p-3 text-right">Button clicks</th>
                        <th scope="col" class="p-3 text-right">Different visitors clicking</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-200" data-listing="{{ $row['listing']->id }}">
                            <th scope="row" class="p-3 font-normal">{{ $row['listing']->title('en') }}</th>
                            <td class="p-3 text-right" data-views>{{ number_format($row['views']) }}</td>
                            <td class="p-3 text-right">{{ $row['listing']->deeplink_url ? number_format($row['clicks']) : '–' }}</td>
                            <td class="p-3 text-right">{{ $row['listing']->deeplink_url ? number_format($row['unique']) : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-sm text-slate-700">A dash means the property has no button link.</p>
    @endif
@endsection
