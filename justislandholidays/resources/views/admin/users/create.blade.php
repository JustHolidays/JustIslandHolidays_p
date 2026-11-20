@extends('layouts.admin', ['title' => $role === 'agent' ? 'Add an agent' : 'Add an owner'])

@section('content')
    <p><a href="{{ route($role === 'agent' ? 'admin.agents.index' : 'admin.users.index') }}" class="inline-flex min-h-11 items-center underline">Back to {{ $role === 'agent' ? 'Agents' : 'Owners' }}</a></p>
    <h1 class="text-2xl font-semibold">{{ $role === 'agent' ? 'Add an agent' : 'Add an owner' }}</h1>
    <p class="mt-1 max-w-3xl">Creates the account. They get an email to set their own password (the link works for 7 days), which also confirms their email address. You can then add their properties with "Add a property for them", or they can add them after signing in. They start on the Free Plan; use Gift A Plan to give them another.</p>

    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 max-w-xl space-y-1 rounded-lg bg-white p-5 shadow-sm">
        @csrf
        <fieldset class="mb-4">
            <legend class="mb-1 font-medium">Account type</legend>
            @foreach (['owner' => 'Owner: lists their own holiday home', 'agent' => 'Agent: lists homes for several owners'] as $value => $label)
                <div class="flex min-h-11 items-center gap-2">
                    <input id="role_{{ $value }}" name="role" type="radio" value="{{ $value }}" class="h-5 w-5" @checked(old('role', $role) === $value)>
                    <label for="role_{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
            @error('role')<p class="text-sm font-medium text-red-800">{{ $message }}</p>@enderror
        </fieldset>

        <x-field name="name" label="Name" autocomplete="off" required />
        <x-field name="email" label="Email" type="email" autocomplete="off" required />
        <x-field name="phone" label="Phone (optional)" type="tel" autocomplete="off" hint="Kept private. Shown on their properties only if they choose to on a paid plan." />

        @if ($agencies->isNotEmpty())
            <div class="mb-4">
                <label for="agency_id" class="mb-1 block font-medium">Agency (agents only, optional)</label>
                <select id="agency_id" name="agency_id" class="min-h-11 w-full rounded border border-slate-400 bg-white px-3">
                    <option value="">None</option>
                    @foreach ($agencies as $agency)<option value="{{ $agency->id }}" @selected((string) old('agency_id') === (string) $agency->id)>{{ $agency->name }}</option>@endforeach
                </select>
            </div>
        @endif

        <div class="mb-4 flex items-start gap-2">
            <input id="invite" name="invite" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0" @checked(old('invite', '1') === '1')>
            <label for="invite">Email them an invitation to set a password now <span class="block text-sm text-slate-600">Untick to send it later from their page, for example after adding their properties.</span></label>
        </div>

        <x-button>Create account</x-button>
    </form>
@endsection
