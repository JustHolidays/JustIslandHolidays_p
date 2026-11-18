@extends('layouts.app', ['title' => 'Set your password'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Set your password</h1>
    <p class="mb-6">Choose a password for your Just Island Holidays account.</p>

    <form method="POST" action="{{ route('invitation.store') }}" class="max-w-md">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" autocomplete="email" required />
        <x-field name="password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." required autofocus />
        <x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <x-button>Set password</x-button>
    </form>
@endsection
