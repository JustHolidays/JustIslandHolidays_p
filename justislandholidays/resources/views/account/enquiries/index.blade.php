@extends('layouts.account', ['title' => 'Enquiries'])

@section('content')
    <h1 class="text-2xl font-semibold">Enquiries</h1>
    <p class="mt-1">Every enquiry is also emailed to you. Reply from your email; mark them here to keep track.</p>

    <nav aria-label="Filter enquiries" class="mt-4">
        <ul class="flex flex-wrap gap-2">
            <li><a href="{{ route('account.enquiries.index') }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] font-semibold' => $status === null, 'border-slate-300' => $status !== null]) @if ($status === null) aria-current="page" @endif>All except spam</a></li>
            @foreach ($statuses as $key => $label)
                <li><a href="{{ route('account.enquiries.index', ['status' => $key]) }}" @class(['inline-flex min-h-11 items-center rounded-full border px-4', 'border-[var(--colour-brand)] font-semibold' => $status === $key, 'border-slate-300' => $status !== $key]) @if ($status === $key) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    @if ($enquiries->isEmpty())
        <p class="mt-6">No enquiries here yet.</p>
    @else
        <div class="mt-6 overflow-x-auto">
            <table class="w-full border-collapse bg-white text-left">
                <caption class="sr-only">Enquiries, newest first</caption>
                <thead>
                    <tr class="border-b border-slate-300">
                        <th scope="col" class="p-3">Received</th>
                        <th scope="col" class="p-3">From</th>
                        <th scope="col" class="p-3">Listing</th>
                        <th scope="col" class="p-3">Dates</th>
                        <th scope="col" class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enquiries as $enquiry)
                        <tr class="border-b border-slate-200">
                            <td class="p-3">{{ $enquiry->created_at->format('j M Y, H:i') }}</td>
                            <td class="p-3"><a href="{{ route('account.enquiries.show', $enquiry) }}" class="font-medium underline">{{ $enquiry->guest_name }}</a></td>
                            <td class="p-3">{{ $enquiry->listing?->title('en') }}</td>
                            <td class="p-3">{{ $enquiry->check_in?->format('j M') ?? '–' }} @if ($enquiry->check_out) to {{ $enquiry->check_out->format('j M Y') }} @endif</td>
                            <td class="p-3">{{ $statuses[$enquiry->status] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $enquiries->links() }}</div>
    @endif
@endsection
