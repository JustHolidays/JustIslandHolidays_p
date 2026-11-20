<x-mail::message>
# New enquiry for {{ $enquiry->listing->title('en') }}

**Enquiry ref:** {{ $enquiry->reference() }}

**From:** {{ $enquiry->guest_name }} ({{ $enquiry->guest_email }})
@if ($enquiry->guest_phone)
**Phone:** {{ $enquiry->guest_phone }}
@endif
@if ($enquiry->check_in || $enquiry->check_out)
**Dates:** {{ $enquiry->check_in?->format('j M Y') ?? '?' }} to {{ $enquiry->check_out?->format('j M Y') ?? '?' }}
@endif
@if ($enquiry->adults !== null || $enquiry->children !== null)
**Guests:** {{ $enquiry->adults ?? 0 }} adults, {{ $enquiry->children ?? 0 }} children
@endif

<x-mail::panel>
{{ $enquiry->message }}
</x-mail::panel>

Reply to this email to answer {{ $enquiry->guest_name }} directly. If you contact us about this enquiry, please quote {{ $enquiry->reference() }}.

The guest agreed to our privacy notice when sending this. Please use their details only to reply about this booking.

Just Island Holidays
</x-mail::message>
