@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Your account' }} · Just Island Holidays</title>
    <style>:root { {{ \App\Support\Theme::cssVariables() }} }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('home', 'en') }}" class="text-lg font-semibold text-[var(--colour-brand)]">Just Island Holidays</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="min-h-11 rounded px-3 text-sm underline">Sign out</button>
            </form>
        </div>
        <nav aria-label="Your account" class="mx-auto max-w-6xl px-4">
            <ul class="flex flex-wrap gap-1 text-sm">
                @php
                    $links = [['account.home', 'Overview']];
                    if ($user->role?->isLister()) {
                        $links[] = ['account.listings.index', 'Listings'];
                        $links[] = ['account.enquiries.index', 'Enquiries'];
                        $links[] = ['account.clicks', 'Clicks'];
                    }
                    if ($user->can('use-billing')) {
                        $links[] = ['account.billing', 'Plan and billing'];
                    }
                    $links[] = ['account.security', 'Two-factor sign-in'];
                @endphp
                @foreach ($links as [$route, $label])
                    <li>
                        <a href="{{ route($route) }}" @class(['inline-flex min-h-11 items-center border-b-2 px-3', 'border-[var(--colour-brand)] font-semibold' => request()->routeIs($route.'*') || request()->routeIs(str_replace('.index', '.*', $route)), 'border-transparent' => ! (request()->routeIs($route.'*') || request()->routeIs(str_replace('.index', '.*', $route)))])
                           @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
                    </li>
                @endforeach
                @can('access-admin')
                    <li><a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center border-b-2 border-transparent px-3">Admin area</a></li>
                @endcan
            </ul>
        </nav>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">{{ session('status') }}</div>
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

        @yield('content')
    </main>
</body>
</html>
