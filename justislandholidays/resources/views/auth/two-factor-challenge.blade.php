@extends('layouts.app', ['title' => 'Two-factor sign-in'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Two-factor sign-in</h1>
    <p class="mb-6">Enter the 6-digit code from your authenticator app.</p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="max-w-md">
        @csrf
        <x-field name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <x-button>Sign in</x-button>
    </form>

    <details class="mt-8 max-w-md">
        <summary class="min-h-11 cursor-pointer py-2 underline">Lost your device? Use a recovery code</summary>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-4">
            @csrf
            <x-field name="recovery_code" label="Recovery code" autocomplete="one-time-code" />
            <x-button>Sign in with recovery code</x-button>
        </form>
    </details>
@endsection
