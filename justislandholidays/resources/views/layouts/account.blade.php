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
                <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-orange-700 px-5 font-medium text-white shadow-sm hover:bg-orange-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-800">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></svg>
                    Sign out
                </button>
            </form>
        </div>
        {{-- The account menu as tabs on the header's bottom edge (20 Nov 2026); the current one is filled. --}}
        <nav aria-label="Your account" class="mx-auto -mb-px max-w-6xl px-4">
            <ul class="flex flex-wrap items-end gap-1 text-sm">
                @php
                    $links = [['account.home', 'Overview']];
                    if ($user->role?->isLister()) {
                        $links[] = ['account.listings.index', 'Listings'];
                        $links[] = ['account.enquiries.index', 'Enquiries'];
                        if ($user->can('use-billing')) {
                            $links[] = ['account.bookings', 'Bookings'];   // owners and agents
                        }
                        $links[] = ['account.clicks', 'Views and clicks'];
                    }
                    if ($user->can('use-billing')) {
                        $links[] = ['account.billing', 'Plan and billing'];
                    }
                    $links[] = ['account.security', 'Password and sign-in'];
                    $links[] = ['account.favourites', 'My favourites'];   // everyone can save homes; always the last tab (20 Nov 2026)
                @endphp
                @foreach ($links as [$route, $label])
                    @php($current = request()->routeIs($route.'*') || request()->routeIs(str_replace('.index', '.*', $route)))
                    <li>
                        <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif
                           @class([
                               'inline-flex min-h-11 items-center rounded-t-lg border border-b-0 px-4 font-medium no-underline',
                               'border-[var(--colour-brand)] bg-[var(--colour-brand)] text-white' => $current,
                               'border-slate-300 bg-slate-50 text-slate-900 hover:bg-white' => ! $current,
                           ])>{{ $label }}</a>
                    </li>
                @endforeach
                @can('access-admin')
                    <li><a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center rounded-t-lg border border-b-0 border-slate-300 bg-slate-50 px-4 font-medium text-slate-900 no-underline hover:bg-white">Admin area</a></li>
                @endcan
            </ul>
        </nav>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            {{-- Fortify answers with codes such as "password-updated": shown as plain words. --}}
            <div role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">{{ [
                'password-updated' => 'Your password has been changed.',
                'two-factor-authentication-enabled' => 'Now scan the code with your authenticator app.',
                'two-factor-authentication-confirmed' => 'Authenticator app sign-in is on.',
                'two-factor-authentication-disabled' => 'Authenticator app sign-in is off.',
            ][session('status')] ?? session('status') }}</div>
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
