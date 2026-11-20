@extends('layouts.admin', ['title' => 'Import and export listings'])

@section('content')
    <p><a href="{{ route('admin.listings.index') }}" class="inline-flex min-h-11 items-center underline">Back to Listing Search</a></p>
    <h1 class="text-2xl font-semibold">Import and export listings</h1>
    <p class="mt-1 max-w-3xl">Download listings as a CSV file to open in Excel, change them there, and upload the file again. Rows with a property reference update that property; rows without one add a new property, as a draft, for the lister whose email is in the row. Photos, facilities, the calendar and rates are not in the file and are not changed. Every upload is checked and shown to you before anything is saved.</p>
    @error('file')<p role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-3 font-medium text-red-800">{{ $message }}</p>@enderror

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="export-heading">
            <h2 id="export-heading" class="text-lg font-semibold">Export</h2>
            <p class="mt-1 text-sm">Includes each property's exact position and the lister's email, so keep the file safe.</p>
            <form method="GET" action="{{ route('admin.listings.csv.export') }}" class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="island" class="mb-1 block text-sm font-medium">Island</label>
                    <select id="island" name="island" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        <option value="">All islands</option>
                        @foreach ($islands as $island)<option value="{{ $island->id }}">{{ $island->translate('name', 'en') }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium">Status</label>
                    <select id="status" name="status" class="min-h-11 rounded border border-slate-400 bg-white px-2">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                    </select>
                </div>
                <x-button>Download CSV</x-button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm" aria-labelledby="import-heading">
            <h2 id="import-heading" class="text-lg font-semibold">Import</h2>
            <p class="mt-1 text-sm">Start from an export, or from the <a href="{{ route('admin.listings.csv.template') }}" class="font-medium underline">blank template</a> (column names and one example row to replace). Save as "CSV UTF-8" in Excel. Up to {{ \App\Admin\ListingCsv::MAX_ROWS }} rows and 2 MB.</p>
            <form method="POST" action="{{ route('admin.listings.csv.upload') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
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
        <p class="mt-1 text-sm">Keep the first row as it is. The columns can be in any order.</p>
        <table class="mt-3 w-full text-left text-sm">
            <caption class="sr-only">CSV columns and what goes in them</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Column</th><th scope="col" class="p-2">What goes in it</th></tr></thead>
            <tbody>
                @foreach ($columns as $column => $help)
                    <tr class="border-b border-slate-100"><th scope="row" class="p-2 font-mono font-normal">{{ $column }}</th><td class="p-2">{{ $help }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
