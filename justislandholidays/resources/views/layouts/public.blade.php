@php
    $lang = app()->getLocale();
    $logo = \App\Support\Appearance::logo();
    $favicons = \App\Support\Appearance::favicons();
    $previewing = \App\Support\Appearance::isPreview();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle ?? 'Just Island Holidays' }}</title>
    @isset($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endisset
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    @if (! empty($noindex))<meta name="robots" content="noindex, follow">@endif
    @foreach ($alternates ?? [] as $altLang => $altUrl)
        <link rel="alternate" hreflang="{{ $altLang }}" href="{{ $altUrl }}">
    @endforeach
    @if ($previewing)<meta name="robots" content="noindex">@endif
    @isset($favicons[32])<link rel="icon" type="image/png" sizes="32x32" href="{{ $favicons[32] }}">@endisset
    @isset($favicons[192])<link rel="icon" type="image/png" sizes="192x192" href="{{ $favicons[192] }}">@endisset
    @isset($favicons[180])<link rel="apple-touch-icon" sizes="180x180" href="{{ $favicons[180] }}">@endisset
    <style>:root { {{ \App\Support\Theme::cssVariables() }} }</style>
    @vite(array_merge(['resources/css/app.css', 'resources/js/app.js'], $extraScripts ?? []))
    @stack('head')
</head>
<body id="top" class="flex min-h-screen flex-col bg-[var(--colour-background)] text-[var(--colour-text)] antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

    @if ($previewing)
        <p role="status" class="bg-amber-300 px-4 py-2 text-center font-medium text-black">Preview of the draft appearance. Visitors still see the published version.</p>
    @endif

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-end justify-between gap-x-3 gap-y-2 px-4 pt-3">
            <a href="{{ route('home') }}" class="mb-3 inline-flex min-h-11 items-center text-xl font-semibold text-[var(--colour-brand)]">
                @if ($logo)
                    <img src="{{ $logo['url'] }}" alt="{{ $logo['alt'] }}" class="h-10 w-auto">
                @else
                    Just Island Holidays
                @endif
            </a>
            {{-- Main menu as tabs sitting on the header's bottom edge (20 Nov 2026). Islands are
                 reached from the home page and search, not the menu. The current page's tab is
                 filled with the brand colour. --}}
            @php
                $tabs = [
                    ['url' => route('search'), 'label' => __('Search'), 'current' => request()->routeIs('search'), 'icon' => null],
                    ['url' => route('owners'), 'label' => __('List your property'), 'current' => request()->routeIs('owners'), 'icon' => null],
                    auth()->check()
                        ? ['url' => route('account.favourites'), 'label' => __('My favourites'), 'current' => false, 'icon' => 'heart']
                        : ['url' => route('login'), 'label' => __('Sign in'), 'current' => false, 'icon' => null],
                ];
            @endphp
            <nav aria-label="{{ __('Main') }}" class="-mb-px">
                <ul class="flex flex-wrap items-end gap-1">
                    @foreach ($tabs as $tab)
                        <li>
                            <a href="{{ $tab['url'] }}" @if ($tab['current']) aria-current="page" @endif @if ($tab['icon'] === 'heart') data-nav-favourites @endif
                               @class([
                                   'inline-flex min-h-11 items-center gap-1 rounded-t-lg border border-b-0 px-4 font-medium no-underline hover:no-underline',
                                   'border-[var(--colour-brand)] bg-[var(--colour-brand)] text-white' => $tab['current'],
                                   'border-slate-300 bg-slate-50 text-[var(--colour-text)] hover:bg-white' => ! $tab['current'],
                               ])>
                                @if ($tab['icon'] === 'heart')
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 text-red-600" fill="currentColor"><path d="M12 21s-7.5-4.6-10-9.3C.4 8.6 2.2 4.5 6.1 4.1c2.2-.2 4 1 5.9 3.2 1.9-2.2 3.7-3.4 5.9-3.2 3.9.4 5.7 4.5 4.1 7.6C19.5 16.4 12 21 12 21z"/></svg>
                                @endif
                                {{ $tab['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <main id="main" class="flex-1">
        @if (session('status'))
            <div class="mx-auto max-w-6xl px-4 pt-6">
                <div role="status" class="rounded border border-green-300 bg-green-50 p-4">{{ session('status') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer image behind the footer content, the same on every page (Frontend Styler).
         A dark layer over it keeps the white text readable (at least 4.5 to 1 over any photo).
         Until an image is added, a plain placeholder with no alt text, as it shows nothing real. --}}
    @php($footerImage = \App\Support\Appearance::footerImage())
    <footer class="relative isolate mt-12 min-h-[180px] overflow-hidden bg-slate-800 text-white md:min-h-[300px]" data-footer-image="{{ $footerImage ? 'photo' : 'placeholder' }}">
        @if ($footerImage)
            <img src="{{ $footerImage['url'] }}" @if ($footerImage['srcset']) srcset="{{ $footerImage['srcset'] }}" sizes="100vw" @endif alt="{{ $footerImage['alt'] }}" loading="lazy" decoding="async" class="absolute inset-0 -z-20 h-full w-full object-cover">
        @else
            <img src="{{ \App\Support\HeaderImage::placeholderUrl() }}" srcset="{{ \App\Support\HeaderImage::placeholderSrcset() }}" sizes="100vw" alt="" loading="lazy" decoding="async" class="absolute inset-0 -z-20 h-full w-full object-cover">
        @endif
        <div class="absolute inset-0 -z-10 bg-black/65" aria-hidden="true"></div>
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 md:grid-cols-2 md:items-center md:py-16">
            <div>
                <p class="font-semibold">Just Island Holidays</p>
                <p class="mt-2 text-sm">{{ __('Holiday homes in Mallorca and Menorca. You contact owners directly; we take no bookings or payments.') }}</p>
                <p class="mt-2 text-sm">{{ __('Some links go to booking partners. We may earn a commission if you book through them, at no extra cost to you.') }}</p>
            </div>
            <nav aria-label="{{ __('Footer') }}">
                @php($footerButton = 'flex min-h-11 w-full items-center justify-center rounded-full bg-orange-700 px-4 text-center font-medium text-white no-underline shadow-sm hover:bg-orange-800 hover:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white')
                <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                    @foreach (['about' => __('About us'), 'contact' => __('Contact'), 'terms' => __('Terms'), 'privacy' => __('Privacy'), 'accessibility' => __('Accessibility')] as $slug => $label)
                        <li><a href="{{ route('pages.show', $slug) }}" class="{{ $footerButton }}">{{ $label }}</a></li>
                    @endforeach
                    <li><a href="{{ route('partners') }}" class="{{ $footerButton }}">{{ __('Our partners') }}</a></li>
                    <li><a href="{{ route('owners') }}" class="{{ $footerButton }}">{{ __('For owners') }}</a></li>
                    <li><a href="{{ route('login') }}" class="{{ $footerButton }}">{{ __('Owner sign in') }}</a></li>
                </ul>
            </nav>
        </div>
    </footer>

    {{-- Back to top (20 Nov 2026): bottom right on every public page. It works as a plain link;
         app.js hides it near the top of the page and shows it once the visitor scrolls down. --}}
    <a href="#top" data-back-to-top
       class="fixed bottom-4 right-4 z-[1000] inline-flex min-h-12 min-w-12 items-center justify-center gap-1 rounded-full bg-orange-700 px-4 font-semibold text-white no-underline shadow-lg transition-opacity hover:bg-orange-800 hover:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-800 focus-visible:ring-2 focus-visible:ring-white">
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M10 3a1 1 0 0 1 .7.3l5 5a1 1 0 0 1-1.4 1.4L11 6.4V16a1 1 0 1 1-2 0V6.4L5.7 9.7a1 1 0 0 1-1.4-1.4l5-5A1 1 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
        <span>{{ __('Back to top') }}</span>
    </a>
</body>
</html>
