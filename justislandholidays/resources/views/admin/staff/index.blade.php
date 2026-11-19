@extends('layouts.admin', ['title' => 'Staff'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Staff</h1>
        <a href="{{ route('admin.staff.create') }}" class="inline-flex min-h-11 items-center rounded bg-slate-900 px-4 font-medium text-white">Add an admin</a>
    </div>
    <p class="mt-1">Keep at least two super admins so no one person is a bottleneck.</p>

    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Admin accounts</caption>
            <thead class="border-b border-slate-300"><tr><th scope="col" class="p-2">Name</th><th scope="col" class="p-2">Email</th><th scope="col" class="p-2">Role</th><th scope="col" class="p-2">Islands</th><th scope="col" class="p-2">Last sign-in</th><th scope="col" class="p-2">Two-factor</th><th scope="col" class="p-2">Account</th></tr></thead>
            <tbody>
                @foreach ($staff as $s)
                    <tr class="border-b border-slate-200">
                        <td class="p-2"><a href="{{ route('admin.staff.edit', $s) }}" class="font-medium underline">{{ $s->name }}</a> @if ($s->id === auth()->id())(you)@endif</td>
                        <td class="p-2">{{ $s->email }}</td>
                        <td class="p-2">{{ $s->role->label() }}</td>
                        <td class="p-2">{{ $s->isSuperAdmin() ? 'All' : ($s->islands->map(fn ($i) => $i->translate('name', 'en'))->implode(', ') ?: 'None') }}</td>
                        <td class="p-2">{{ $s->last_login_at?->format('j M Y H:i') ?? 'Never' }}</td>
                        <td class="p-2">{{ $s->hasConfirmedTwoFactor() ? 'Authenticator app' : 'Code by email' }}</td>
                        <td class="p-2">{{ $s->isDeactivated() ? 'Deactivated' : 'Active' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
