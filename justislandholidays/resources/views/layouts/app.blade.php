<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Account' }} · Just Island Holidays</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="text-lg font-semibold">Just Island Holidays</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 rounded px-3 text-sm underline">Sign out</button>
                </form>
            @endauth
        </div>
    </header>

    <main id="main" class="mx-auto max-w-3xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded border border-sky-300 bg-sky-50 p-4">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-6 rounded border border-red-300 bg-red-50 p-4">
                <p class="font-semibold">Please check the form.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>
