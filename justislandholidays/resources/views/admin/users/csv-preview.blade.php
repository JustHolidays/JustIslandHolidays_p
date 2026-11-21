@extends('layouts.admin', ['title' => 'Check the import'])

@section('content')
    <p><a href="{{ route('admin.users.csv') }}" class="inline-flex min-h-11 items-center underline">Back to Import and export</a></p>
    <h1 class="text-2xl font-semibold">Check the import</h1>
    <p class="mt-1">{{ $name }}: nothing has been saved yet.</p>

    <dl class="mt-4 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4" data-csv-counts>
        @foreach (['add' => 'New accounts', 'update' => 'Updated', 'unchanged' => 'No changes', 'errors' => 'With problems (skipped)'] as $key => $label)
            <div class="rounded-lg bg-white p-3 shadow-sm">
                <dt class="text-sm text-slate-600">{{ $label }}</dt>
                <dd class="text-2xl font-semibold @if ($key === 'errors' && $counts['errors'] > 0) text-red-800 @endif" data-count="{{ $key }}">{{ $counts[$key] }}</dd>
            </div>
        @endforeach
    </dl>
    @if ($ignored !== [])
        <p class="mt-3 text-sm">These columns are not used and were left out: {{ implode(', ', $ignored) }}.</p>
    @endif

    @if ($counts['add'] + $counts['update'] > 0)
        <form method="POST" action="{{ route('admin.users.csv.apply', $token) }}" class="mt-5"
              onsubmit="return confirm(@js('Save '.$counts['add'].' new and '.$counts['update'].' updated accounts? No emails are sent. Rows with problems are skipped.'))">
            @csrf
            <x-button>Save {{ $counts['add'] + $counts['update'] }} {{ $counts['add'] + $counts['update'] === 1 ? 'account' : 'accounts' }}</x-button>
            <span class="ml-2 text-sm text-slate-700">No emails are sent. Rows with problems are skipped; you can fix them and upload again.</span>
        </form>
    @else
        <p class="mt-5 font-medium">There is nothing to save. Fix the problems below and upload the file again.</p>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Each row of the file and what it would do</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Line</th><th scope="col" class="p-2">Account</th><th scope="col" class="p-2">What happens</th><th scope="col" class="p-2">Details</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-100 align-top @if ($row['errors'] !== []) bg-red-50 @endif" data-csv-line="{{ $row['line'] }}">
                        <td class="p-2">{{ $row['line'] }}</td>
                        <td class="p-2">{{ $row['name'] ?: '(no name)' }} @if ($row['email'] !== '')<span class="block text-slate-600">{{ $row['email'] }}</span>@endif</td>
                        <td class="p-2 whitespace-nowrap font-medium">
                            @if ($row['errors'] !== []) <span class="text-red-800">Skipped</span>
                            @elseif ($row['action'] === 'add') New {{ $row['data']['role'] }}
                            @elseif ($row['action'] === 'update') Update
                            @else No changes
                            @endif
                        </td>
                        <td class="p-2">
                            @if ($row['errors'] !== [])
                                <ul class="list-disc pl-5 text-red-800">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                            @endif
                            @if ($row['changes'] !== [])
                                <ul class="space-y-0.5">
                                    @foreach ($row['changes'] as $field => [$old, $new])
                                        <li><span class="font-mono">{{ $field }}</span>: {{ \Illuminate\Support\Str::limit((string) ($old ?? '–'), 60) }} → <strong>{{ \Illuminate\Support\Str::limit((string) ($new ?? '–'), 60) }}</strong></li>
                                    @endforeach
                                </ul>
                            @endif
                            @foreach ($row['notes'] as $note)<p class="text-slate-700">{{ $note }}</p>@endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
