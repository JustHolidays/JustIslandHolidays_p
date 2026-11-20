@props(['listing', 'title'])
{{--
    Share buttons (20 Nov 2026). Each points to /share/{network}/{ref} on our own site, which
    passes the visitor on to the network's share page: ad and tracker blockers hide links that
    point straight at those pages (and anything marked "share"), so the markers here use other
    names. No social network scripts or cookies load on our pages. Copy link and the phone's own
    share sheet need script and only appear where the browser supports them.
--}}
@php
    $url = $listing->publicUrl();
    $lang = app()->getLocale();
    $links = [
        ['facebook', 'Facebook', 'bg-[#1877F2]', '<path d="M14 8h3V4h-3c-2.8 0-4 1.7-4 4.2V10H7v4h3v8h4v-8h3l1-4h-4V8.6c0-.4.2-.6.6-.6z"/>'],
        ['whatsapp', 'WhatsApp', 'bg-[#128C4A]', '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.6 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.1.1-1.8-.1-4.1-1.3-6.6-5.3-6.8-5.6-.2-.3-1.4-1.9-1.4-3.6s.9-2.6 1.3-2.9c.3-.3.7-.4 1-.4h.7c.2 0 .5 0 .7.6l1 2.3c.1.2.1.4 0 .6l-.4.7-.5.5c-.2.2-.3.4-.1.7.2.3.9 1.5 2 2.4 1.4 1.2 2.5 1.6 2.9 1.7.3.2.5.1.7-.1l.9-1.1c.2-.3.4-.2.7-.1l2.2 1c.3.2.5.3.6.4.1.2.1.8-.2 1.5z"/>'],
        ['x', 'X', 'bg-black', '<path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/>'],
        ['pinterest', 'Pinterest', 'bg-[#BD081C]', '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.3-5.4s-.3-.7-.3-1.6c0-1.5.9-2.6 1.9-2.6.9 0 1.4.7 1.4 1.5 0 .9-.6 2.3-.9 3.6-.3 1.1.5 2 1.6 2 1.9 0 3.4-2 3.4-4.9 0-2.6-1.9-4.4-4.5-4.4-3.1 0-4.9 2.3-4.9 4.7 0 .9.4 1.9.8 2.5.1.1.1.2.1.3l-.3 1.2c0 .2-.2.3-.4.2-1.4-.6-2.2-2.6-2.2-4.2 0-3.4 2.5-6.6 7.2-6.6 3.8 0 6.7 2.7 6.7 6.3 0 3.7-2.4 6.8-5.6 6.8-1.1 0-2.2-.6-2.5-1.3l-.7 2.6c-.2 1-.9 2.2-1.4 2.9A10 10 0 1 0 12 2z"/>'],
    ];
    $button = 'inline-flex h-11 w-11 items-center justify-center rounded-full text-white no-underline shadow-sm hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2', 'data-pass-on' => '']) }}>
    <span class="text-sm font-medium" id="pass-on-label">{{ __('Share') }}</span>
    <ul class="flex flex-wrap items-center gap-2" aria-labelledby="pass-on-label">
        @foreach ($links as [$network, $name, $colour, $icon])
            <li>
                <a href="{{ route('share', ['network' => $network, 'ref' => $listing->public_ref, 'l' => $lang]) }}" target="_blank" rel="noopener nofollow" class="{{ $button }} {{ $colour }}" data-pass-on-to="{{ $network }}">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">{!! $icon !!}</svg>
                    <span class="sr-only">{{ __('Share on :site', ['site' => $name]) }} {{ __('(opens in a new tab)') }}</span>
                </a>
            </li>
        @endforeach
        <li>
            <a href="mailto:?subject={{ rawurlencode($title) }}&amp;body={{ rawurlencode($title) }}%0A{{ rawurlencode($url) }}" class="{{ $button }} bg-slate-700" data-pass-on-to="email">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.1L4 7.2V17h16V7.2l-8 4.9zM19.4 7H4.6l7.4 4.5L19.4 7z"/></svg>
                <span class="sr-only">{{ __('Share by email') }}</span>
            </a>
        </li>
        <li hidden data-pass-on-copy-item>
            <button type="button" class="{{ $button }} bg-orange-700" data-pass-on-copy="{{ $url }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>
                <span class="sr-only">{{ __('Copy link') }}</span>
            </button>
        </li>
        <li hidden data-pass-on-native-item>
            <button type="button" class="inline-flex min-h-11 items-center rounded-full border border-slate-300 bg-white px-4 font-medium shadow-sm" data-pass-on-native data-title="{{ $title }}" data-url="{{ $url }}">{{ __('More…') }}</button>
        </li>
    </ul>
    <span role="status" class="text-sm text-green-800" data-pass-on-status></span>
</div>
