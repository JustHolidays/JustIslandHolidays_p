{{-- Sortable column heading. Keeps the other filters in the URL. --}}
@props(['key', 'label', 'default' => null])

@php
    $current = request('sort', $default);
    $dir = request('dir', 'desc');
    $isCurrent = $current === $key;
    $nextDir = $isCurrent && $dir === 'asc' ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]);
@endphp

<th scope="col" class="p-2 text-left" @if ($isCurrent) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $url }}" class="inline-flex min-h-11 items-center gap-1 underline decoration-dotted">
        {{ $label }}
        @if ($isCurrent)<span aria-hidden="true">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
    </a>
</th>
