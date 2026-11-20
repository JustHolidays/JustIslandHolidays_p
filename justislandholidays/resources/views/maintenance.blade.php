@php
    // Maintenance page (20 Nov 2026): a full-screen island photo with the message on top.
    $hero = \App\Support\Appearance::heroes(false)->first();
    $photo = $hero ? \Illuminate\Support\Facades\Storage::disk('public')->url($hero->image_path) : \App\Support\HeaderImage::placeholderUrl();
    $logo = \App\Support\Appearance::logo();
    $favicons = \App\Support\Appearance::favicons();
    $until = $settings['until'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $settings['title'] }} | Just Island Holidays</title>
    @isset($favicons[32])<link rel="icon" type="image/png" sizes="32x32" href="{{ $favicons[32] }}">@endisset
    <style>:root { {{ \App\Support\Theme::cssVariables() }} }</style>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-900 text-white antialiased">
    @if ($preview)
        <p role="status" class="relative z-20 bg-amber-300 px-4 py-2 text-center font-medium text-black">
            Preview: this is what visitors see while maintenance mode is on.
            <a href="{{ route('admin.maintenance.edit') }}" class="underline">Back to Maintenance mode</a>
        </p>
    @endif

    <main class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-16">
        <img src="{{ $photo }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-linear-to-b from-black/50 via-black/55 to-black/75" aria-hidden="true"></div>

        <div class="w-full max-w-2xl rounded-2xl bg-white/10 p-8 text-center shadow-2xl ring-1 ring-white/20 backdrop-blur-md sm:p-12">
            <div class="mb-6 flex justify-center">
                @if ($logo)
                    <img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}" class="h-14 w-auto rounded bg-white/90 p-2">
                @else
                    <p class="text-2xl font-semibold tracking-wide">Just Island Holidays</p>
                @endif
            </div>

            {{-- A small sun and waves, drawn here so nothing else needs loading. --}}
            <svg aria-hidden="true" viewBox="0 0 120 60" class="mx-auto mb-6 h-16 w-32">
                <circle cx="60" cy="30" r="16" fill="#fbbf24"/>
                <g stroke="#fbbf24" stroke-width="3" stroke-linecap="round">
                    <path d="M60 4v6M34 30h-6M92 30h-6M41 11l4 4M79 11l-4 4"/>
                </g>
                <path d="M0 46c10 0 10-6 20-6s10 6 20 6 10-6 20-6 10 6 20 6 10-6 20-6 10 6 20 6v14H0z" fill="#38bdf8" opacity=".9"/>
                <path d="M0 52c10 0 10-5 20-5s10 5 20 5 10-5 20-5 10 5 20 5 10-5 20-5 10 5 20 5v8H0z" fill="#0ea5e9"/>
            </svg>

            <h1 class="text-3xl font-semibold leading-tight sm:text-4xl">{{ $settings['title'] }}</h1>
            <div class="mt-5 space-y-3 text-lg text-white/90">
                @foreach (preg_split('/\n\s*\n/', trim($settings['message'])) as $paragraph)
                    <p>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
            </div>

            @if ($until && $until->isFuture())
                <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-orange-700 px-5 py-2 font-semibold">
                    We expect to be back by {{ $until->format('l j F, H:i') }}
                </p>
            @endif

            <p class="mt-8 text-sm text-white/80">
                Own a holiday home with us? You can still <a href="{{ route('login') }}" class="font-medium text-white underline">sign in to your account</a>.
            </p>
        </div>
    </main>
</body>
</html>
