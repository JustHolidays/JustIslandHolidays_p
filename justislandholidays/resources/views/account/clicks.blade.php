@extends('layouts.account', ['title' => 'Clicks'])

@section('content')
    <h1 class="text-2xl font-semibold">Check availability clicks</h1>
    <p class="mt-1">Clicks on your Check availability buttons since {{ $since->format('j M Y') }}. Known bots are left out. A visitor counts once per listing in any 30 minutes.</p>

    @if ($rows->isEmpty())
        <p class="mt-6">None of your listings has a booking link yet, so there are no clicks to show.</p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="w-full border-collapse bg-white text-left">
                <caption class="sr-only">Clicks per listing, last 30 days</caption>
                <thead>
                    <tr class="border-b border-slate-300">
                        <th scope="col" class="p-3">Listing</th>
                        <th scope="col" class="p-3 text-right">Clicks</th>
                        <th scope="col" class="p-3 text-right">Different visitors</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-200">
                            <th scope="row" class="p-3 font-normal">{{ $row['listing']->title('en') }}</th>
                            <td class="p-3 text-right">{{ $row['clicks'] }}</td>
                            <td class="p-3 text-right">{{ $row['unique'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
