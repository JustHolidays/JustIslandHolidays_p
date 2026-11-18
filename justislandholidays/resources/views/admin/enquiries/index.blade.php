@extends('layouts.admin', ['title' => 'Enquiries'])

@section('content')
    <h1 class="text-2xl font-semibold">Enquiries</h1>
    <p class="mt-1">Read only, for complaints and spam. Owners reply from their own email. Opening an enquiry is recorded in the audit log.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm" aria-label="Filter enquiries">
        <div><label for="q" class="block text-sm font-medium">Guest name or email</label><input id="q" name="q" type="search" value="{{ request('q') }}" class="min-h-11 rounded border border-slate-400 px-2"></div>
        <div><label for="status" class="block text-sm font-medium">Status</label>
            <select id="status" name="status" class="min-h-11 rounded border border-slate-400 bg-white px-2"><option value="">Any</option>@foreach (['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed', 'spam' => 'Spam'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
        <button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Filter</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Enquiries, newest first</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Received</th><th scope="col" class="p-2">Listing</th><th scope="col" class="p-2">Guest</th><th scope="col" class="p-2">Message</th><th scope="col" class="p-2">Status</th></tr></thead>
            <tbody>
                @forelse ($enquiries as $enquiry)
                    @php
                        $recent = $enquiry->created_at->gte($recentSince);
                    @endphp
                    <tr @class(['border-b border-slate-200', 'bg-sky-50' => $recent])>
                        <td class="p-2 whitespace-nowrap">{{ $enquiry->created_at->format('j M Y H:i') }} @if ($recent)<span class="ml-1 rounded bg-sky-800 px-2 py-0.5 text-xs text-white">Recent</span>@endif</td>
                        <td class="p-2">{{ $enquiry->listing?->title('en') }}<br><span class="text-slate-600">{{ $enquiry->listing?->island?->translate('name', 'en') }}</span></td>
                        <td class="p-2"><a href="{{ route('admin.enquiries.show', $enquiry) }}" class="font-medium underline">{{ $enquiry->guest_name }}</a></td>
                        <td class="p-2">{{ \Illuminate\Support\Str::limit($enquiry->message, 80) }}</td>
                        <td class="p-2">{{ ucfirst($enquiry->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4">No enquiries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enquiries->links() }}</div>
@endsection
