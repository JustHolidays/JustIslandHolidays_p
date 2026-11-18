@extends('layouts.app', ['title' => 'Reset your password'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Reset your password</h1>
    <p class="mb-6">Enter your email and we will send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('password.email') }}" class="max-w-md">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-button>Send reset link</x-button>
    </form>
@endsection
