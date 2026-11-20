@extends('layouts.account', ['title' => 'Bookings'])

@section('content')
    <h1 class="text-2xl font-semibold">Bookings</h1>
    <p class="mt-1">Every stay booked on your properties' calendars. Stays booked here with Confirm Booking show the guest and the enquiry; the others show where they came from. To change a stay, open the property's Booked dates.</p>

    <p class="mt-2 text-sm text-slate-700">On stays you marked yourself you can add a short guest reference, for example "Smith family", just for you. Back-to-back stays you marked at the same property can be joined into one stay. Guest references are removed 24 months after the stay.</p>
    @error('bookings')<p class="mt-2 text-sm font-medium text-red-800">{{ $message }}</p>@enderror

    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-4" aria-labelledby="download-heading">
        <h2 id="download-heading" class="font-semibold">Download your booking list</h2>
        <p class="mt-1 text-sm text-slate-700">Every stay with at least one night between the two dates, as a PDF to print or send, or an Excel spreadsheet. It can include guests' names, so keep it safe.</p>
        <form method="GET" action="{{ route('account.bookings.download') }}" class="mt-3 flex flex-wrap items-end gap-3">
            <x-field name="from" label="From" type="date" :value="old('from', today()->toDateString())" required />
            <x-field name="to" label="To" type="date" :value="old('to', today()->addMonths(3)->toDateString())" required />
            <fieldset class="mb-4">
                <legend class="mb-1 font-medium">Format</legend>
                <div class="flex gap-4">
                    @foreach (['pdf' => 'PDF', 'xlsx' => 'Excel'] as $value => $label)
                        <div class="flex min-h-11 items-center gap-2">
                            <input id="format_{{ $value }}" name="format" type="radio" value="{{ $value }}" class="h-5 w-5" @checked(old('format', 'pdf') === $value)>
                            <label for="format_{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
            <div class="mb-4"><x-button>Download</x-button></div>
        </form>
        @error('format')<p class="text-sm font-medium text-red-800">{{ $message }}</p>@enderror
    </section>

    <nav aria-label="Which bookings" class="mt-4">
        <ul class="flex flex-wrap gap-2">
            <li><a href="{{ route('account.bookings') }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] font-semibold' => ! $past, 'border-slate-300' => $past]) @if (! $past) aria-current="page" @endif>Upcoming</a></li>
            <li><a href="{{ route('account.bookings', ['when' => 'past']) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] font-semibold' => $past, 'border-slate-300' => ! $past]) @if ($past) aria-current="page" @endif>Past stays</a></li>
        </ul>
    </nav>

    @if ($bookings->isEmpty())
        <p class="mt-6">
            @if (! $hasCalendar)
                Bookings come from the booking calendar, which is part of the paid plans. <a href="{{ route('account.billing') }}" class="underline">See the plans</a>
            @elseif ($past)
                No past stays.
            @else
                No upcoming stays. Mark dates on a property's calendar, or use Confirm Booking on an enquiry.
            @endif
        </p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="w-full border-collapse bg-white text-left">
                <caption class="sr-only">{{ $past ? 'Past stays, latest first' : 'Upcoming stays, soonest first' }}</caption>
                <thead>
                    <tr class="border-b-2 border-slate-300">
                        <th scope="col" class="p-3">Arrival</th>
                        <th scope="col" class="p-3">Departure</th>
                        <th scope="col" class="p-3">Nights</th>
                        <th scope="col" class="p-3">Property</th>
                        <th scope="col" class="p-3">Guest</th>
                        <th scope="col" class="p-3">Enquiry</th>
                        <th scope="col" class="p-3">Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bookings as $booking)
                        <tr class="border-b border-slate-200" data-booking="{{ $booking->id }}">
                            <td class="p-3 whitespace-nowrap">{{ $booking->check_in->format('D j M Y') }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $booking->check_out->format('D j M Y') }}</td>
                            <td class="p-3">{{ $booking->check_in->diffInDays($booking->check_out) }}</td>
                            <td class="p-3">
                                @if ($booking->listing)
                                    <a href="{{ route('account.listings.edit', [$booking->listing, 'bookings']) }}" class="underline">{{ $booking->listing->title('en') }}</a>
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($booking->enquiry)
                                    {{ $booking->enquiry->guest_name }}
                                @elseif ($booking->feed_id === null)
                                    {{-- A stay the owner marked: their own short guest reference. --}}
                                    @php($refBag = $errors->getBag('ref'.$booking->id))
                                    <form data-keep-scroll method="POST" action="{{ route('account.bookings.reference', $booking) }}" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <label for="ref-{{ $booking->id }}" class="sr-only">Guest reference for {{ $booking->check_in->format('j M Y') }}</label>
                                        <input id="ref-{{ $booking->id }}" name="guest_ref" type="text" maxlength="100" value="{{ $refBag->any() ? old('guest_ref') : $booking->guest_ref }}" placeholder="Add a reference"
                                               class="min-h-11 w-44 rounded border border-slate-400 px-2 text-sm" @if ($refBag->any()) aria-invalid="true" aria-describedby="ref-{{ $booking->id }}-error" @endif>
                                        <button type="submit" class="min-h-11 rounded border border-slate-400 px-3 text-sm">Save<span class="sr-only"> guest reference</span></button>
                                    </form>
                                    @if ($refBag->any())<p id="ref-{{ $booking->id }}-error" class="mt-1 text-sm font-medium text-red-800">{{ $refBag->first('guest_ref') }}</p>@endif
                                @else
                                    <span aria-hidden="true">–</span><span class="sr-only">Not known</span>
                                @endif
                            </td>
                            <td class="p-3 whitespace-nowrap font-mono">
                                @if ($booking->enquiry)
                                    @can('view', $booking->enquiry)
                                        <a href="{{ route('account.enquiries.show', $booking->enquiry) }}" class="underline">{{ $booking->enquiry->reference() }}</a>
                                    @else
                                        {{ $booking->enquiry->reference() }}
                                    @endcan
                                @else
                                    <span aria-hidden="true">–</span><span class="sr-only">None</span>
                                @endif
                            </td>
                            <td class="p-3 text-sm">
                                {{ $booking->sourceLabel() }}
                                @isset($runs[$booking->id])
                                    @php($runEnd = \App\Models\CalendarBooking::find(end($runs[$booking->id]))?->check_out)
                                    <form data-keep-scroll method="POST" action="{{ route('account.bookings.join', $booking) }}" class="mt-2"
                                          onsubmit="return confirm(@js('Join '.count($runs[$booking->id]).' back-to-back stays into one stay, '.$booking->check_in->format('j M Y').' to '.$runEnd?->format('j M Y').'?'))">
                                        @csrf
                                        <button type="submit" class="min-h-11 rounded bg-orange-700 px-3 font-semibold text-white hover:bg-orange-800">Join {{ count($runs[$booking->id]) }} stays<span class="sr-only"> from {{ $booking->check_in->format('j M Y') }} to {{ $runEnd?->format('j M Y') }}</span></button>
                                        <span class="mt-1 block text-xs text-slate-600">Back-to-back to {{ $runEnd?->format('j M Y') }}</span>
                                    </form>
                                @endisset
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bookings->links() }}</div>
    @endif
@endsection
