@extends('layouts.admin', ['title' => 'Import and export owners and agents'])

@section('content')
    <p><a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center underline">Back to Owners</a></p>
    <h1 class="text-2xl font-semibold">Import and export owners and agents</h1>
    <p class="mt-1 max-w-3xl">Download owner and agent accounts as a CSV file to open in Excel, or upload a file to add accounts and update names, phone numbers and agencies. Rows are matched by email. New accounts start on the Free Plan with no password: no email is sent, so use Send invitation on each person's page when they are ready. Email addresses, roles and plans are never changed by the file. Every upload is checked and shown to you before anything is saved.</p>
    @error('file')<p role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-3 font-medium text-red-800">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="export-heading">
            <h2 id="export-heading" class="text-lg font-semibold">Export</h2>
            <p class="mt-1 text-sm">Includes names, email addresses and phone numbers, so keep the file safe and delete it when you are done.</p>
            <form method="GET" action="{{ route('admin.users.csv.export') }}" class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="role" class="mb-1 block text-sm font-medium">Accounts</label>
                    <select id="role" name="role" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        <option value="">Owners and agents</option>
                        <option value="owner">Owners only</option>
                        <option value="agent">Agents only</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium">Status</label>
                    <select id="status" name="status" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="deactivated">Deactivated</option>
                    </select>
                </div>
                <x-button>Download CSV</x-button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="import-heading">
            <h2 id="import-heading" class="text-lg font-semibold">Import</h2>
            <p class="mt-1 text-sm">Start from an export, or from the <a href="{{ route('admin.users.csv.template') }}" class="font-medium underline">blank template</a> (column names and two example rows to replace). Save as "CSV UTF-8" in Excel. Up to {{ \App\Admin\UserCsv::MAX_ROWS }} rows and 2 MB.</p>
            <form method="POST" action="{{ route('admin.users.csv.upload') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                <div>
                    <label for="file" class="mb-1 block font-medium">CSV file</label>
                    <input id="file" name="file" type="file" accept=".csv,text/csv" required class="block min-h-11">
                </div>
                <x-button>Check the file</x-button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-labelledby="columns-heading">
        <h2 id="columns-heading" class="text-lg font-semibold">The columns</h2>
        <p class="mt-1 text-sm">Keep the first row as it is. The columns can be in any order. Passwords are never in the file.</p>
        <table class="mt-3 w-full text-left text-sm">
            <caption class="sr-only">CSV columns and what goes in them</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Column</th><th scope="col" class="p-2">What goes in it</th></tr></thead>
            <tbody>
                @foreach ($columns as $column => $help)
                    <tr class="border-b border-slate-100"><th scope="row" class="p-2 font-mono font-normal">{{ $column }}</th><td class="p-2">{{ $help }}</td></tr>
                @endforeach
                @foreach ($exportOnly as $column => $help)
                    <tr class="border-b border-slate-100"><th scope="row" class="p-2 font-mono font-normal">{{ $column }}</th><td class="p-2">{{ $help }} In exports only; ignored when importing.</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
