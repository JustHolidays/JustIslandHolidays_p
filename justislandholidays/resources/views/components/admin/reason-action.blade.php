{{--
    A button that opens a small form asking for a reason, with a confirmation.
    Works without JavaScript (details/summary).
--}}
@props(['action', 'label', 'confirm' => null, 'field' => 'reason', 'hint' => 'Sent by email to the person affected.', 'method' => 'POST', 'danger' => true])

<details class="rounded border border-slate-300 bg-white">
    <summary @class(['flex min-h-11 cursor-pointer items-center px-4 font-medium', 'text-red-800' => $danger])>{{ $label }}</summary>
    <form method="POST" action="{{ $action }}" class="space-y-2 p-4" @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
        @csrf
        @if ($method !== 'POST') @method($method) @endif
        {{ $slot }}
        @php($id = 'reason-'.md5($action.$label))
        <label for="{{ $id }}" class="block font-medium">Reason</label>
        <p id="{{ $id }}-hint" class="text-sm text-slate-600">{{ $hint }}</p>
        <textarea id="{{ $id }}" name="{{ $field }}" rows="3" maxlength="500" required aria-describedby="{{ $id }}-hint" class="block w-full rounded border border-slate-400 px-3 py-2"></textarea>
        <button type="submit" @class(['min-h-11 rounded px-4 font-medium text-white', 'bg-red-800' => $danger, 'bg-slate-900' => ! $danger])>{{ $label }}</button>
    </form>
</details>
