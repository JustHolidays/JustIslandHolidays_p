<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Availability: :title', ['title' => $listing->title('en')]) }}</title>
    <style>:root { {{ \App\Support\Theme::cssVariables() }} }</style>
    @vite(['resources/css/app.css'])
</head>
{{-- Embedded on other websites: no menu or footer, just the calendar and a link to the listing. --}}
<body class="bg-white p-3 text-[var(--colour-text)] antialiased" data-calendar-embed>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="font-semibold">{{ __('Availability') }}</p>
        <nav aria-label="{{ __('Calendar months') }}" class="flex gap-2 text-sm">
            @if ($offset > 0)<a href="{{ route('calendar.embed', $listing->public_ref) }}?months={{ max(0, $offset - $count) }}&amp;show={{ $count }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">{{ __('Earlier months') }}</a>@endif
            @if ($offset < 9)<a href="{{ route('calendar.embed', $listing->public_ref) }}?months={{ min(9, $offset + $count) }}&amp;show={{ $count }}" class="inline-flex min-h-11 items-center rounded border border-slate-400 px-3">{{ __('Later months') }}</a>@endif
        </nav>
    </div>

    @include('partials.calendar-months', ['months' => $months])

    <p class="mt-3 text-sm">
        <a href="{{ $listing->publicUrl('en') }}" target="_blank" rel="noopener" class="underline">{{ __('See :title on Just Island Holidays', ['title' => $listing->title('en')]) }}</a>
    </p>
</body>
</html>
