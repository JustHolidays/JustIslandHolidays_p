@php
    $ctx = new \App\Admin\AdminContext(request());
    $admin = $ctx->admin();
    $adminIslands = $ctx->islands();
    $selectedIsland = $ctx->selectedIslandId();
    $nav = [
        ['admin.dashboard', 'Dashboard', null],
        ['admin.listings.index', 'Listings', null],
        ['admin.licences.index', 'Licence checks', null],
        ['admin.deeplinks.index', 'Deeplinks', null],
        ['admin.enquiries.index', 'Enquiries', null],
        ['admin.users.index', 'Owners and agents', null],
        ['admin.affiliates.index', 'Affiliates', 'manage-affiliates'],
        ['admin.clicks.index', 'Click reports', null],
        ['admin.islands.index', 'Islands and resorts', null],
        ['admin.pages.index', 'Content pages', null],
        ['admin.staff.index', 'Staff', 'manage-staff'],
        ['admin.appearance.edit', 'Appearance', 'manage-appearance'],
        ['admin.plans.index', 'Plans', 'manage-plans'],
        ['admin.audit.index', 'Audit log', 'view-audit-log'],
    ];
    $section = fn ($route) => preg_replace('/\.(index|edit|show)$/', '', $route);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · Admin · Just Island Holidays</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <header class="bg-slate-900 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center text-lg font-semibold">Just Island Holidays · Admin</a>
            <div class="flex flex-wrap items-center gap-3">
                @if ($adminIslands->count() > 1)
                    <form method="POST" action="{{ route('admin.island') }}" class="flex items-center gap-2">
                        @csrf
                        <label for="admin-island" class="text-sm">Island</label>
                        {{-- No auto-submit on change (WCAG 3.2.2): the admin presses Show. --}}
                        <select id="admin-island" name="island" class="min-h-11 rounded bg-white px-2 text-slate-900">
                            <option value="">All my islands</option>
                            @foreach ($adminIslands as $i)
                                <option value="{{ $i->id }}" @selected($selectedIsland === $i->id)>{{ $i->translate('name', 'en') }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="min-h-11 rounded bg-white px-3 font-medium text-slate-900">Show</button>
                    </form>
                @elseif ($adminIslands->count() === 1)
                    <span class="text-sm">Island: {{ $adminIslands->first()->translate('name', 'en') }}</span>
                @endif
                <span class="text-sm">{{ $admin->name }} ({{ $admin->role->label() }})</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 rounded px-3 text-sm underline">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 lg:flex-row">
        <nav aria-label="Admin" class="lg:w-56 lg:shrink-0">
            <ul class="flex flex-wrap gap-1 lg:flex-col">
                @foreach ($nav as [$route, $label, $ability])
                    @if (! $ability || $admin->can($ability))
                        @php
                            $active = request()->routeIs($section($route).'*');
                        @endphp
                        <li>
                            <a href="{{ route($route) }}" @class(['flex min-h-11 items-center rounded px-3', 'bg-white font-semibold shadow-sm' => $active, 'hover:bg-white' => ! $active])
                               @if ($active) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <main id="main" class="min-w-0 flex-1">
            @if (session('status'))
                <div role="status" class="mb-4 rounded border border-green-300 bg-green-50 p-4">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-4 rounded border border-red-300 bg-red-50 p-4">
                    <p class="font-semibold">That did not work.</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
