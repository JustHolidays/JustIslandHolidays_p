Account closure request

From: {{ $requester->name }} ({{ $requester->email }}), {{ strtolower($requester->role->label()) }}
Asked on: {{ $requester->closure_requested_at?->format('j M Y, H:i') }}
@if ($requester->closure_reason)

{{ $requester->closure_reason }}
@endif

Close the account, or decline with a reason, on Account closures: {{ route('admin.closures.index') }}
Reply to this email to answer {{ $requester->name }} directly.

Just Island Holidays
