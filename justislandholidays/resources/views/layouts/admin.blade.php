@php
    $ctx = new \App\Admin\AdminContext(request());
    $admin = $ctx->admin();
    $adminIslands = $ctx->islands();
    $selectedIsland = $ctx->selectedIslandId();
    // Menu order (set 19 Nov 2026). [route, label, ability]; a null route is a heading.
    $nav = [
        ['admin.dashboard', 'Dashboard', null],
        ['admin.plans.index', 'Plans', 'manage-plans'],
        ['admin.listings.index', 'Listing Search', null],
        ['admin.licences.index', 'Licence checks', null],
        ['admin.enquiries.index', 'Enquiries', null],
        ['admin.users.index', 'Owners', null],
        ['admin.agents.index', 'Agents', null],   // a dropdown of agents, each with a page of their properties
        ['admin.affiliates.index', 'Affiliates', 'manage-affiliates'],   // a dropdown of active affiliates
        ['admin.ads.index', 'Affiliate Adverts', 'manage-ads'],
        ['admin.clicks.index', 'Click reports', null],
        [null, 'Locations', null],
        ['admin.islands.index', 'Islands and resorts', null],   // a dropdown of the admin's active islands
        ['admin.locations.create', 'Add a location', 'manage-islands'],
        [null, 'Settings', null],
        ['admin.staff.index', 'Staff', 'manage-staff'],
        ['admin.pages.index', 'Content pages', null],
        ['admin.property-types.index', 'Property types', 'manage-property-types'],
        ['admin.appearance.edit', 'Frontend Styler', 'manage-appearance'],
        ['admin.admin-colours.edit', 'Admin Styler', 'manage-appearance'],
        ['admin.seo.edit', 'SEO', 'manage-seo'],
        ['admin.maintenance.edit', 'Maintenance mode', 'manage-maintenance'],
        ['admin.closures.index', 'Account closures', 'manage-account-closures'],
        ['admin.paypal-settings.edit', 'PayPal Settings', 'manage-paypal-settings'],
        ['admin.audit.index', 'Audit log', 'view-audit-log'],
    ];
    // Leave out what this admin cannot open, then any heading with nothing left under it.
    $nav = array_values(array_filter($nav, fn ($item) => ! $item[2] || $admin->can($item[2])));
    $nav = array_values(array_filter($nav, fn ($item, $i) => $item[0] !== null || (isset($nav[$i + 1]) && $nav[$i + 1][0] !== null), ARRAY_FILTER_USE_BOTH));
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
    {{-- Admin colours (super admins set them on the Admin Styler page). --}}
    <style>{!! \App\Support\AdminTheme::css() !!}</style>
    @stack('head')
</head>
<body class="min-h-screen bg-[var(--admin-page)] text-slate-900 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <header class="bg-[var(--admin-header)] text-[var(--admin-header-text)]">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-11 items-center text-lg font-semibold">Just Island Holidays · Admin</a>
            <div class="flex flex-wrap items-center gap-3">
                @if ($adminIslands->count() > 1)
                    <form method="POST" action="{{ route('admin.island') }}" class="flex items-center gap-2">
                        @csrf
                        <label for="admin-island" class="text-sm">Island</label>
                        {{-- No auto-submit on change (WCAG 3.2.2): the admin presses Show. --}}
                        <select id="admin-island" name="island" class="min-h-11 rounded bg-white px-2 text-slate-900">
                            <option value="">All Locations</option>
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
                @php
                    // An agent's account page (users.show) belongs under Agents, not Owners.
                    $routeUser = request()->route('user');
                    $viewingAgent = $routeUser instanceof \App\Models\User && $routeUser->role === \App\Enums\Role::Agent;
                @endphp
                @foreach ($nav as [$route, $label, $ability])
                    @if ($route === null)
                        {{-- Menu heading (not a link). Full width so it starts its own row on small screens. --}}
                        <li class="w-full px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide" data-menu-heading>{{ $label }}</li>
                        @continue
                    @endif
                    @if ($route === 'admin.users.index')
                        @php
                            $onOwners = request()->routeIs('admin.users.*') && ! $viewingAgent;
                        @endphp
                        <li>
                            <a href="{{ route($route) }}" @class(['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold shadow-sm' => $onOwners, 'hover:bg-[var(--admin-highlight)]' => ! $onOwners])
                               @if ($onOwners) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                        @continue
                    @endif
                    @if ($route === 'admin.agents.index')
                        @php
                            $inAgents = request()->routeIs('admin.agents.*') || $viewingAgent;
                            $currentAgentId = $viewingAgent ? $routeUser->id : null;
                            $navAgents = \App\Http\Controllers\Admin\UserController::peopleQuery($ctx, \App\Enums\Role::Agent)
                                ->whereNull('deactivated_at')->with('agency:id,name')->orderBy('name')->get(['id', 'name', 'agency_id']);
                            $agentLink = fn ($on) => ['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold' => $on, 'hover:bg-[var(--admin-highlight)]' => ! $on];
                        @endphp
                        <li class="w-full lg:w-auto">
                            <details @if ($inAgents) open @endif class="group">
                                <summary @class(['flex min-h-11 cursor-pointer list-none items-center justify-between rounded px-3 [&::-webkit-details-marker]:hidden', 'bg-[var(--admin-highlight)] font-semibold shadow-sm' => $inAgents, 'hover:bg-[var(--admin-highlight)]' => ! $inAgents])>
                                    {{ $label }}
                                    <span aria-hidden="true" class="ml-2 transition-transform group-open:rotate-180">▾</span>
                                </summary>
                                <ul class="mt-1 space-y-1 pl-3">
                                    <li><a href="{{ route('admin.agents.index') }}" @class($agentLink(request()->routeIs('admin.agents.index'))) @if (request()->routeIs('admin.agents.index')) aria-current="page" @endif>All agents</a></li>
                                    @forelse ($navAgents as $navAgent)
                                        @php
                                            $agentHere = $currentAgentId === $navAgent->id && request()->routeIs('admin.agents.show');
                                        @endphp
                                        <li>
                                            <a href="{{ route('admin.agents.show', $navAgent) }}" @class($agentLink($agentHere || $currentAgentId === $navAgent->id)) @if ($agentHere) aria-current="page" @endif>
                                                <span>{{ $navAgent->name }}@if ($navAgent->agency)<span class="block text-xs font-normal text-slate-600">{{ $navAgent->agency->name }}</span>@endif</span>
                                            </a>
                                        </li>
                                    @empty
                                        <li class="px-3 py-2 text-sm text-slate-600">No agents yet</li>
                                    @endforelse
                                </ul>
                            </details>
                        </li>
                        @continue
                    @endif
                    @if ($route === 'admin.affiliates.index')
                        @if ($admin->can($ability))
                            @php
                                $inAffiliates = request()->routeIs('admin.affiliates.*');
                                $routeAffiliate = request()->route('affiliate');
                                $currentAffiliateId = $routeAffiliate instanceof \App\Models\User ? $routeAffiliate->id : (int) $routeAffiliate;
                                $navAffiliates = \App\Models\User::where('role', \App\Enums\Role::Affiliate->value)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']);
                                $sub = fn ($on) => ['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold' => $on, 'hover:bg-[var(--admin-highlight)]' => ! $on];
                            @endphp
                            <li class="w-full lg:w-auto">
                                <details @if ($inAffiliates) open @endif class="group">
                                    <summary @class(['flex min-h-11 cursor-pointer list-none items-center justify-between rounded px-3 [&::-webkit-details-marker]:hidden', 'bg-[var(--admin-highlight)] font-semibold shadow-sm' => $inAffiliates, 'hover:bg-[var(--admin-highlight)]' => ! $inAffiliates])>
                                        {{ $label }}
                                        <span aria-hidden="true" class="ml-2 transition-transform group-open:rotate-180">▾</span>
                                    </summary>
                                    <ul class="mt-1 space-y-1 pl-3">
                                        @php
                                            $onAll = request()->routeIs('admin.affiliates.index', 'admin.affiliates.create');
                                        @endphp
                                        <li><a href="{{ route('admin.affiliates.index') }}" @class($sub($onAll)) @if (request()->routeIs('admin.affiliates.index')) aria-current="page" @endif>All affiliates</a></li>
                                        @foreach ($navAffiliates as $navAffiliate)
                                            @php
                                                $thisOne = $inAffiliates && $currentAffiliateId === $navAffiliate->id;
                                            @endphp
                                            <li>
                                                <a href="{{ route('admin.affiliates.show', $navAffiliate) }}" @class($sub($thisOne && request()->routeIs('admin.affiliates.show')))
                                                   @if ($thisOne && request()->routeIs('admin.affiliates.show')) aria-current="page" @endif>{{ $navAffiliate->name }}</a>
                                                @if ($thisOne)
                                                    <ul class="mt-1 pl-3">
                                                        <li><a href="{{ route('admin.affiliates.deeplinks', $navAffiliate) }}" @class($sub(request()->routeIs('admin.affiliates.deeplinks')))
                                                               @if (request()->routeIs('admin.affiliates.deeplinks')) aria-current="page" @endif>Deeplinks</a></li>
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </li>
                        @endif
                        @continue
                    @endif
                    @if ($route === 'admin.islands.index')
                        @php
                            // Opens by itself on any island or resort page. A native disclosure: works by keyboard, no script.
                            $inIslands = request()->routeIs('admin.islands.*', 'admin.areas.*');
                            $currentIsland = request()->route('island');
                            $currentIslandId = $currentIsland instanceof \App\Models\Island ? $currentIsland->id : (int) $currentIsland;
                            if (request()->routeIs('admin.areas.*') && request()->route('area') instanceof \App\Models\Area) {
                                $currentIslandId = request()->route('area')->island_id;
                            }
                        @endphp
                        <li class="w-full lg:w-auto">
                            <details @if ($inIslands) open @endif class="group">
                                <summary @class(['flex min-h-11 cursor-pointer list-none items-center justify-between rounded px-3 [&::-webkit-details-marker]:hidden', 'bg-[var(--admin-highlight)] font-semibold shadow-sm' => $inIslands, 'hover:bg-[var(--admin-highlight)]' => ! $inIslands])>
                                    {{ $label }}
                                    <span aria-hidden="true" class="ml-2 transition-transform group-open:rotate-180">▾</span>
                                </summary>
                                <ul class="mt-1 space-y-1 pl-3">
                                    <li>
                                        <a href="{{ route('admin.islands.index') }}" @class(['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold' => request()->routeIs('admin.islands.index'), 'hover:bg-[var(--admin-highlight)]' => ! request()->routeIs('admin.islands.index')])
                                           @if (request()->routeIs('admin.islands.index')) aria-current="page" @endif>All islands</a>
                                    </li>
                                    @foreach ($adminIslands->where('is_active', true) as $navIsland)
                                        @php
                                            $here = $inIslands && ! request()->routeIs('admin.islands.index') && $currentIslandId === $navIsland->id;
                                        @endphp
                                        <li>
                                            <a href="{{ route('admin.islands.show', $navIsland) }}" @class(['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold' => $here, 'hover:bg-[var(--admin-highlight)]' => ! $here])
                                               @if ($here && request()->routeIs('admin.islands.show')) aria-current="page" @endif>{{ $navIsland->translate('name', 'en') }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                        @continue
                    @endif
                    @if (! $ability || $admin->can($ability))
                        @php
                            $active = request()->routeIs($section($route).'*');
                        @endphp
                        <li>
                            <a href="{{ route($route) }}" @class(['flex min-h-11 items-center rounded px-3', 'bg-[var(--admin-highlight)] font-semibold shadow-sm' => $active, 'hover:bg-[var(--admin-highlight)]' => ! $active])
                               @if ($active) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <main id="main" class="min-w-0 flex-1">
            @if (\App\Support\Maintenance::isOn())
                {{-- Reminder on every admin page while visitors see the maintenance page. --}}
                <div role="status" class="mb-4 rounded border-2 border-amber-500 bg-amber-50 p-3 font-medium" data-maintenance-banner>
                    Maintenance mode is on: visitors see the maintenance page.
                    @can('manage-maintenance')<a href="{{ route('admin.maintenance.edit') }}" class="underline">Switch it off</a>@endcan
                </div>
            @endif
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
