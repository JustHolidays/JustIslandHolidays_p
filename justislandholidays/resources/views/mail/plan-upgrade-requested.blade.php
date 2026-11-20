<x-mail::message>
# Plan upgrade request

**From:** {{ $requester->name }} ({{ $requester->email }}), {{ strtolower($requester->role->label()) }}
**Current plan:** {{ $currentPlan }}
**Would like:** {{ $wanted }}
**Listings held now:** {{ $listingsHeld }}

@if ($note)
<x-mail::panel>
{{ $note }}
</x-mail::panel>
@endif

Reply to this email to answer {{ $requester->name }} directly. To give them a plan without payment, use Gift A Plan in Plans and subscriptions.

Just Island Holidays
</x-mail::message>
