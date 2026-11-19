@extends('layouts.admin', ['title' => 'Admin Styler'])

@section('content')
    <h1 class="text-2xl font-semibold">Admin Styler</h1>
    <p class="mt-1 max-w-3xl">The colours of these admin pages: the header bar, the page background and the highlighted menu item. Changes apply straight away for all staff. To change the public website's colours, use <a href="{{ route('admin.appearance.edit') }}" class="underline">Frontend Styler</a>.</p>

    <form method="POST" action="{{ route('admin.admin-colours.update') }}" class="mt-6 rounded-lg bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <div class="grid gap-4 md:grid-cols-2">
            @foreach (\App\Support\AdminTheme::LABELS as $name => $label)
                @php
                    $check = $checks[$name];
                    $value = old($name, $colours[$name]);
                @endphp
                <div class="rounded border border-slate-200 p-3">
                    <label for="ac-{{ $name }}" class="block font-medium">{{ $label }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="color" value="{{ $value }}" aria-label="{{ $label }} colour picker" class="h-11 w-14 cursor-pointer rounded border border-slate-400"
                               oninput="document.getElementById('ac-{{ $name }}').value = this.value">
                        <input id="ac-{{ $name }}" name="{{ $name }}" type="text" value="{{ $value }}" pattern="#[0-9a-fA-F]{6}" required class="min-h-11 w-28 rounded border border-slate-400 px-2 font-mono">
                    </div>
                    <p @class(['mt-2 text-sm', 'font-semibold text-red-800' => ! $check['passes'], 'text-green-800' => $check['passes']])>
                        Contrast with {{ $check['against'] }}: {{ number_format($check['ratio'], 2) }} to 1 {{ $check['passes'] ? '– passes' : '– too low, needs 4.5' }}
                    </p>
                    <p class="text-xs text-slate-600">Original: <span class="font-mono">{{ \App\Support\AdminTheme::DEFAULTS[$name] }}</span></p>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button>Save admin colours</x-button>
            <button type="submit" name="reset" value="1" formnovalidate class="min-h-11 rounded border border-slate-400 px-4">Back to the original colours</button>
        </div>
    </form>
@endsection
