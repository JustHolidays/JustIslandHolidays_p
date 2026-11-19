{{--
    Breadcrumb trail. Pass items as [['name' => ..., 'url' => ...], ...]; the last item is the
    current page and is not a link. Every part lines up on one 44-pixel row (tap targets), the
    separators are decorative, and long trails wrap neatly on phones.
--}}
@props(['items'])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'mb-2 text-sm']) }}>
    <ol class="flex flex-wrap items-center gap-x-1 text-slate-700">
        @foreach ($items as $crumb)
            <li class="flex min-h-11 items-center">
                @if (! $loop->last)
                    <a href="{{ $crumb['url'] }}" class="inline-flex min-h-11 items-center underline decoration-slate-400 underline-offset-4 hover:text-[var(--colour-brand)] hover:decoration-current">{{ $crumb['name'] }}</a>
                    <svg class="ml-1 h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                    </svg>
                @else
                    <span aria-current="page" class="font-medium text-[var(--colour-text)]">{{ $crumb['name'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
