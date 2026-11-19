@extends('layouts.admin', ['title' => $staff->exists ? 'Edit '.$staff->name : 'Add an admin'])

@php
    $assigned = old('islands', $staff->exists ? $staff->islands->pluck('id')->all() : []);
    $role = old('role', $staff->role?->value ?? 'island_admin');
    $self = $staff->exists && $staff->id === auth()->id();
@endphp

@section('content')
    <p><a href="{{ route('admin.staff.index') }}" class="inline-flex min-h-11 items-center underline">Back to staff</a></p>
    <h1 class="text-2xl font-semibold">{{ $staff->exists ? $staff->name : 'Add an admin' }}</h1>
    @unless ($staff->exists)
        <p class="mt-1">They receive an email to set a password. Each time they sign in, they also get a 6-digit code by email, or use an authenticator app if they set one up.</p>
    @endunless

    <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="mt-6 max-w-2xl rounded-lg bg-white p-5 shadow-sm">
        @csrf
        @if ($staff->exists) @method('PUT') @endif
        <x-field name="name" label="Name" :value="$staff->name" required />
        <x-field name="email" label="Email" type="email" :value="$staff->email" required />

        <fieldset class="mb-4" @disabled($self)>
            <legend class="mb-1 font-medium">Role @if ($self)<span class="text-sm font-normal">(you cannot change your own role)</span>@endif</legend>
            @foreach (['island_admin' => 'Island admin: moderates listings and licences for chosen islands', 'super_admin' => 'Super admin: everything, on every island'] as $v => $l)
                <div class="flex min-h-11 items-center gap-2"><input id="role-{{ $v }}" type="radio" name="role" value="{{ $v }}" class="h-5 w-5" @checked($role === $v)><label for="role-{{ $v }}">{{ $l }}</label></div>
            @endforeach
        </fieldset>
        @if ($self)<input type="hidden" name="role" value="{{ $role }}">@endif

        <fieldset class="mb-4">
            <legend class="mb-1 font-medium">Islands (island admins only)</legend>
            @foreach ($islands as $island)
                <div class="flex min-h-11 items-center gap-2"><input id="island-{{ $island->id }}" type="checkbox" name="islands[]" value="{{ $island->id }}" class="h-5 w-5" @checked(in_array($island->id, $assigned))><label for="island-{{ $island->id }}">{{ $island->translate('name', 'en') }}</label></div>
            @endforeach
        </fieldset>

        <x-button>{{ $staff->exists ? 'Save' : 'Create and send invitation' }}</x-button>
    </form>

    @if ($staff->exists && ! $self)
        <section class="mt-6 flex max-w-2xl flex-wrap items-start gap-3" aria-label="Account actions">
            <form method="POST" action="{{ route('admin.staff.sign-out', $staff) }}" onsubmit="return confirm('Sign {{ $staff->name }} out on every device?')">@csrf<button class="min-h-11 rounded border border-slate-400 bg-white px-4">Sign out everywhere</button></form>
            <form method="POST" action="{{ route('admin.staff.reset-2fa', $staff) }}" onsubmit="return confirm('Remove their authenticator app? They will get sign-in codes by email instead.')">@csrf<button class="min-h-11 rounded border border-slate-400 bg-white px-4">Remove authenticator app (use email codes)</button></form>
            <form method="POST" action="{{ route('admin.staff.invite', $staff) }}">@csrf<button class="min-h-11 rounded border border-slate-400 bg-white px-4">Send a new invitation</button></form>
            @if ($staff->isDeactivated())
                <form method="POST" action="{{ route('admin.staff.reactivate', $staff) }}">@csrf<button class="min-h-11 rounded bg-slate-900 px-4 font-medium text-white">Reactivate</button></form>
            @else
                <x-admin.reason-action :action="route('admin.staff.deactivate', $staff)" label="Deactivate" confirm="Deactivate this admin? They are signed out at once." hint="Recorded in the audit log and emailed to them." />
            @endif
        </section>
    @endif
@endsection
