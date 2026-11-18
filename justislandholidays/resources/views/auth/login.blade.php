@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Sign in</h1>

    <form method="POST" action="{{ route('login') }}" class="max-w-md">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="mb-6 flex min-h-11 items-center gap-2">
            <input id="remember" name="remember" type="checkbox" class="h-5 w-5">
            <label for="remember">Keep me signed in</label>
        </div>

        <x-button>Sign in</x-button>
    </form>

    <p class="mt-6"><a class="underline" href="{{ route('password.request') }}">Forgotten your password?</a></p>
    <p class="mt-2"><a class="underline" href="{{ route('register') }}">List your property: create an account</a></p>
@endsection
