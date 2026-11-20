@props(['listing', 'size' => 'card'])
{{--
    The favourites heart (20 Nov 2026). Signed in: a button that saves the home (solid red heart)
    or removes it (outline). Signed out: a link to sign in or create a free account, after which
    the home is saved. At least 44 pixels to press; the page keeps its place after saving.
--}}
@php
    $saved = \App\Listings\Favourites::has(auth()->user(), $listing);
    $title = $listing->title();
    $label = $saved ? __('Remove :title from your favourites', ['title' => $title]) : __('Save :title to your favourites', ['title' => $title]);
    $box = $size === 'page'
        ? 'inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-300 bg-white px-4 font-medium shadow-sm hover:border-red-600'
        : 'inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow hover:bg-white';
    $icon = $saved ? 'h-6 w-6 text-red-600' : 'h-6 w-6 text-orange-700';
@endphp
<div {{ $attributes->merge(['data-favourite' => $saved ? 'saved' : 'not-saved']) }}>
    @auth
        <form data-keep-scroll method="POST" action="{{ route('favourites.toggle', $listing->public_ref) }}">
            @csrf
            <button type="submit" class="{{ $box }}" aria-pressed="{{ $saved ? 'true' : 'false' }}" title="{{ $label }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="{{ $icon }}" fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                    <path d="M12 21s-7.5-4.6-10-9.3C.4 8.6 2.2 4.5 6.1 4.1c2.2-.2 4 1 5.9 3.2 1.9-2.2 3.7-3.4 5.9-3.2 3.9.4 5.7 4.5 4.1 7.6C19.5 16.4 12 21 12 21z"/>
                </svg>
                @if ($size === 'page')
                    <span>{{ $saved ? __('Saved to favourites') : __('Save to favourites') }}</span>
                @else
                    <span class="sr-only">{{ $label }}</span>
                @endif
            </button>
        </form>
    @else
        <a href="{{ route('favourites.sign-in', $listing->public_ref) }}" class="{{ $box }} no-underline" title="{{ $label }}" rel="nofollow">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                <path d="M12 21s-7.5-4.6-10-9.3C.4 8.6 2.2 4.5 6.1 4.1c2.2-.2 4 1 5.9 3.2 1.9-2.2 3.7-3.4 5.9-3.2 3.9.4 5.7 4.5 4.1 7.6C19.5 16.4 12 21 12 21z"/>
            </svg>
            @if ($size === 'page')
                <span class="text-[var(--colour-text)]">{{ __('Save to favourites') }}</span>
            @else
                <span class="sr-only">{{ $label }}</span>
            @endif
        </a>
    @endauth
</div>
