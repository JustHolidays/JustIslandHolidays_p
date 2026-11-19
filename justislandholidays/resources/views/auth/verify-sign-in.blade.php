@extends('layouts.app', ['title' => 'Finish signing in'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Finish signing in</h1>

    @if (session('status'))
        <p role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">{{ session('status') }}</p>
    @endif

    @if ($usesApp)
        <p class="mb-6">Enter the 6-digit code from your authenticator app.</p>

        <form method="POST" action="{{ route('second-factor.verify') }}" class="max-w-md">
            @csrf
            <x-field name="code" label="Code" inputmode="numeric" autocomplete="one-time-code" autofocus />
            <x-button>Sign in</x-button>
        </form>

        <details class="mt-8 max-w-md">
            <summary class="min-h-11 cursor-pointer py-2 underline">Lost your phone? Use a recovery code</summary>
            <form method="POST" action="{{ route('second-factor.verify') }}" class="mt-4">
                @csrf
                <x-field name="recovery_code" label="Recovery code" autocomplete="one-time-code" />
                <x-button>Sign in with recovery code</x-button>
            </form>
        </details>
    @else
        @if ($sendFailed)
            <p role="alert" class="mb-6 rounded border border-red-300 bg-red-50 p-4">Too many codes have been sent in the last hour. Wait a while, then reload this page.</p>
        @else
            <p class="mb-6">We have emailed a 6-digit code to <strong>{{ $email }}</strong>. It works once, for {{ $minutes }} minutes. Check your spam folder if it has not arrived.</p>
        @endif

        <form method="POST" action="{{ route('second-factor.verify') }}" class="max-w-md">
            @csrf
            <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" autofocus />
            <x-button>Sign in</x-button>
        </form>

        <form method="POST" action="{{ route('second-factor.resend') }}" class="mt-6">
            @csrf
            <button type="submit" class="min-h-11 underline">Send a new code</button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="min-h-11 underline">Sign out</button>
    </form>
@endsection
