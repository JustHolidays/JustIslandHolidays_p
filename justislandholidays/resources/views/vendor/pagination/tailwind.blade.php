{{-- Pagination: 44 px targets, the current page marked for screen readers, plain labels. --}}
@if ($paginator->hasPages())
    @php
        $link = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded border border-slate-300 bg-white px-3 hover:border-slate-700';
    @endphp
    <nav aria-label="{{ __('Pages') }}" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-700">
            {{ __('Showing :first to :last of :total', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $link }} cursor-not-allowed opacity-50" aria-disabled="true">{{ __('Previous') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}">{{ __('Previous') }}</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inline-flex min-h-11 min-w-11 items-center justify-center" aria-hidden="true">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $link }} border-slate-900 bg-slate-900 font-semibold text-white"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="{{ $link }}"><span class="sr-only">{{ __('Page') }} </span>{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}">{{ __('Next') }}</a>
                @else
                    <span class="{{ $link }} cursor-not-allowed opacity-50" aria-disabled="true">{{ __('Next') }}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
