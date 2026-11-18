@extends('layouts.app', ['title' => 'Confirm your password'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Confirm your password</h1>
    <p class="mb-6">For your security, enter your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="max-w-md">
        @csrf
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <x-button>Continue</x-button>
    </form>
@endsection
