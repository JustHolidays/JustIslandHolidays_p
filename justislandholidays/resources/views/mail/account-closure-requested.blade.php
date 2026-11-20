<x-mail::message>
# Account closure request

**From:** {{ $requester->name }} ({{ $requester->email }}), {{ strtolower($requester->role->label()) }}
**Asked on:** {{ $requester->closure_requested_at?->format('j M Y, H:i') }}

@if ($requester->closure_reason)
<x-mail::panel>
{{ $requester->closure_reason }}
</x-mail::panel>
@endif

<x-mail::button :url="route('admin.closures.index')">
Open Account closures
</x-mail::button>

Close the account there, or decline with a reason. Reply to this email to answer {{ $requester->name }} directly.

Just Island Holidays
</x-mail::message>
