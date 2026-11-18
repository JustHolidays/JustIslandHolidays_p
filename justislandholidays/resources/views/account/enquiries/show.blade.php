@extends('layouts.account', ['title' => 'Enquiry from '.$enquiry->guest_name])

@section('content')
    <p><a href="{{ route('account.enquiries.index') }}" class="inline-flex min-h-11 items-center underline">Back to enquiries</a></p>
    <h1 class="text-2xl font-semibold">Enquiry from {{ $enquiry->guest_name }}</h1>
    <p class="mt-1">For {{ $enquiry->listing?->title('en') }}, received {{ $enquiry->created_at->format('j M Y, H:i') }}</p>

    <dl class="mt-6 grid max-w-2xl gap-3 rounded-lg border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-600">Email</dt><dd><a href="mailto:{{ $enquiry->guest_email }}" class="underline">{{ $enquiry->guest_email }}</a></dd></div>
        @if ($enquiry->guest_phone)<div><dt class="text-sm text-slate-600">Phone</dt><dd>{{ $enquiry->guest_phone }}</dd></div>@endif
        <div><dt class="text-sm text-slate-600">Dates</dt><dd>{{ $enquiry->check_in?->format('j M Y') ?? 'Not given' }} @if ($enquiry->check_out) to {{ $enquiry->check_out->format('j M Y') }} @endif</dd></div>
        <div><dt class="text-sm text-slate-600">Guests</dt><dd>{{ $enquiry->adults ?? '?' }} adults, {{ $enquiry->children ?? 0 }} children</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-slate-600">Message</dt><dd class="whitespace-pre-line">{{ $enquiry->message }}</dd></div>
    </dl>

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
