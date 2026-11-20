@props(['size' => 'card'])
{{--
    "Wheelchair friendly" (20 Nov 2026): the international symbol of access, white on blue,
    on cards and property pages of homes that list the Wheelchair friendly facility.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full bg-[#1d4ed8] font-medium text-white '.($size === 'page' ? 'px-3 py-1 text-sm' : 'h-8 w-8 justify-center shadow'), 'data-wheelchair' => '', 'title' => __('Wheelchair friendly')]) }}>
    <svg aria-hidden="true" viewBox="0 0 24 24" class="{{ $size === 'page' ? 'h-5 w-5' : 'h-5 w-5' }}" fill="currentColor">
        <circle cx="12" cy="3.5" r="2"/>
        <path d="M10.5 7.2a1.3 1.3 0 0 1 2.6-.3l.4 3.1h3.3a1.2 1.2 0 0 1 0 2.4h-3l.2 1.6H17a1.3 1.3 0 0 1 1.2.8l1.9 4.6 1-.4a1.2 1.2 0 1 1 .9 2.2l-2.1.9a1.3 1.3 0 0 1-1.7-.7l-1.9-4.6h-4.9a1.3 1.3 0 0 1-1.3-1.1z"/>
        <path d="M7.6 10.4a1 1 0 0 1 .5 1.9 4.5 4.5 0 1 0 6 5.8 1 1 0 1 1 1.9.6 6.5 6.5 0 1 1-8.4-8.3z"/>
    </svg>
    @if ($size === 'page')
        <span>{{ __('Wheelchair friendly') }}</span>
    @else
        <span class="sr-only">{{ __('Wheelchair friendly') }}</span>
    @endif
</span>
