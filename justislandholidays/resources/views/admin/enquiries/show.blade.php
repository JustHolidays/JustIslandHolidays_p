@extends('layouts.admin', ['title' => 'Enquiry '.$enquiry->reference()])

@section('content')
    <p><a href="{{ route('admin.enquiries.index') }}" class="inline-flex min-h-11 items-center underline">Back to enquiries</a></p>
    <h1 class="text-2xl font-semibold">Enquiry {{ $enquiry->reference() }} from {{ $enquiry->guest_name }}</h1>
    <p class="mt-1">For <a href="{{ route('admin.listings.show', $enquiry->listing_id) }}" class="underline">{{ $enquiry->listing?->title('en') }}</a> (owner: {{ $enquiry->owner?->name }}), received {{ $enquiry->created_at->format('j M Y H:i') }}. Status: {{ ucfirst($enquiry->status) }}.</p>

    <dl class="mt-6 grid max-w-3xl gap-3 rounded-lg bg-white p-5 shadow-sm sm:grid-cols-2">
        <div><dt class="text-sm text-slate-600">Email</dt><dd>{{ $enquiry->guest_email }}</dd></div>
        <div><dt class="text-sm text-slate-600">Phone</dt><dd>{{ $enquiry->guest_phone ?: '–' }}</dd></div>
        <div><dt class="text-sm text-slate-600">Dates</dt><dd>{{ $enquiry->check_in?->format('j M Y') ?? '–' }} to {{ $enquiry->check_out?->format('j M Y') ?? '–' }}</dd></div>
        <div><dt class="text-sm text-slate-600">Emailed to owner</dt><dd>{{ $enquiry->email_sent_at?->format('j M Y H:i') ?? 'Not yet' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-slate-600">Message</dt><dd class="whitespace-pre-line">{{ $enquiry->message }}</dd></div>
    </dl>

    @if ($enquiry->status !== 'spam')
        <form method="POST" action="{{ route('admin.enquiries.spam', $enquiry) }}" class="mt-4" onsubmit="return confirm('Mark this enquiry as spam?')">
            @csrf
            <button class="min-h-11 rounded border border-red-800 px-4 text-red-800">Mark as spam</button>
        </form>
    @endif
@endsection
