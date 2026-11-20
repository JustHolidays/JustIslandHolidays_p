@extends('layouts.account', ['title' => 'Enquiry from '.$enquiry->guest_name])

@section('content')
    <p><a href="{{ route('account.enquiries.index') }}" class="inline-flex min-h-11 items-center underline">Back to enquiries</a></p>
    <h1 class="text-2xl font-semibold">Enquiry {{ $enquiry->reference() }} from {{ $enquiry->guest_name }}</h1>
    <p class="mt-1">For {{ $enquiry->listing?->title('en') }}, received {{ $enquiry->created_at->format('j M Y, H:i') }}</p>

    <dl class="mt-6 grid max-w-2xl gap-3 rounded-lg border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-600">Email</dt><dd><a href="mailto:{{ $enquiry->guest_email }}" class="underline">{{ $enquiry->guest_email }}</a></dd></div>
        @if ($enquiry->guest_phone)<div><dt class="text-sm text-slate-600">Phone</dt><dd>{{ $enquiry->guest_phone }}</dd></div>@endif
        <div><dt class="text-sm text-slate-600">Dates</dt><dd>{{ $enquiry->check_in?->format('j M Y') ?? 'Not given' }} @if ($enquiry->check_out) to {{ $enquiry->check_out->format('j M Y') }} @endif</dd></div>
        <div><dt class="text-sm text-slate-600">Guests</dt><dd>{{ $enquiry->adults ?? '?' }} adults, {{ $enquiry->children ?? 0 }} children</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-slate-600">Message</dt><dd class="whitespace-pre-line">{{ $enquiry->message }}</dd></div>
    </dl>

    {{-- Confirm Booking: puts the requested dates on the property's calendar (paid plans). --}}
    <section class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="book-heading" data-booking-state="{{ $booking['state'] }}">
        <h2 id="book-heading" class="text-lg font-semibold">Booking</h2>
        @error('booking')<p class="mt-2 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
        @if ($booking['state'] === 'ready')
            <p class="mt-1 text-sm text-slate-700">Agreed with {{ $enquiry->guest_name }}? Book {{ $enquiry->check_in->format('j M Y') }} to {{ $enquiry->check_out->format('j M Y') }} ({{ trans_choice(':count night|:count nights', $enquiry->check_in->diffInDays($enquiry->check_out)) }}) on your calendar. Visitors then see those dates as booked, and this enquiry is closed. This does not email the guest; reply to them yourself.</p>
            <form method="POST" action="{{ route('account.enquiries.confirm-booking', $enquiry) }}" class="mt-3"
                  onsubmit="return confirm(@js('Confirm this booking for '.$enquiry->guest_name.'?'."\n\n".$enquiry->check_in->format('j M Y').' to '.$enquiry->check_out->format('j M Y').' will be booked on your calendar, and enquiry '.$enquiry->reference().' will be closed.'))">
                @csrf
                <button type="submit" class="min-h-11 rounded bg-orange-700 px-5 font-semibold text-white hover:bg-orange-800">Confirm Booking</button>
            </form>
        @elseif ($booking['state'] === 'booked')
            <p class="mt-1">{{ $booking['message'] }}
                @if ($enquiry->listing?->hasCalendar())
                    <a href="{{ route('account.listings.edit', [$enquiry->listing, 'bookings']) }}" class="underline">See your booked dates</a>
                @endif
            </p>
        @else
            <p class="mt-1 text-sm text-slate-700">{{ $booking['message'] }}</p>
        @endif
    </section>

    <form method="POST" action="{{ route('account.enquiries.status', $enquiry) }}" class="mt-6 flex max-w-2xl flex-wrap items-end gap-3">
        @csrf @method('PATCH')
        <div>
            <label for="status" class="mb-1 block font-medium">Status</label>
            <select id="status" name="status" class="min-h-11 rounded border border-slate-400 bg-white px-3">
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($enquiry->status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <x-button>Save status</x-button>
    </form>
@endsection
