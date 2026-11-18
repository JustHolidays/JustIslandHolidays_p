@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'autocomplete' => null, 'id' => null])

@php
    // The id defaults to the field name; pass id when the same form appears more than once on a page.
    $fieldId = $id ?? $name;
@endphp

<div class="mb-4">
    <label for="{{ $fieldId }}" class="mb-1 block font-medium">{{ $label }}</label>
    @if ($hint)
        <p id="{{ $fieldId }}-hint" class="mb-1 text-sm text-slate-600">{{ $hint }}</p>
    @endif
    <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($hint) aria-describedby="{{ $fieldId }}-hint" @endif
           @error($name) aria-invalid="true" aria-errormessage="{{ $fieldId }}-error" @enderror
           {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded border border-slate-400 bg-white px-3']) }}>
    @error($name)
        <p id="{{ $fieldId }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
