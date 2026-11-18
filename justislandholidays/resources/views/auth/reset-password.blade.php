@extends('layouts.app', ['title' => 'Choose a new password'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="max-w-md">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="Email" type="email" autocomplete="email" :value="$request->email" required />
        <x-field name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <x-button>Save password</x-button>
    </form>
@endsection
