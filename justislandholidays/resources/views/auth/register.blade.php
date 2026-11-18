@extends('layouts.app', ['title' => 'Create an account'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Create an account</h1>
    <p class="mb-6">List your holiday home on Just Island Holidays. You will confirm your email address before you publish.</p>

    <form method="POST" action="{{ route('register') }}" class="max-w-md">
        @csrf
        <x-field name="name" label="Your name" autocomplete="name" required autofocus />
        <x-field name="email" label="Email" type="email" autocomplete="email" required />

        <fieldset class="mb-4">
            <legend class="mb-1 font-medium">I am</legend>
            <div class="flex min-h-11 items-center gap-2">
                <input id="account_type_owner" name="account_type" type="radio" value="owner" class="h-5 w-5" @checked(old('account_type', 'owner') === 'owner')>
                <label for="account_type_owner">A property owner</label>
            </div>
            <div class="flex min-h-11 items-center gap-2">
                <input id="account_type_agent" name="account_type" type="radio" value="agent" class="h-5 w-5" @checked(old('account_type') === 'agent')>
                <label for="account_type_agent">An agent or manager with several properties</label>
            </div>
            @error('account_type')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-field name="password" label="Password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
        <x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <x-button>Create account</x-button>
    </form>

    <p class="mt-6"><a class="underline" href="{{ route('login') }}">Already have an account? Sign in</a></p>
@endsection
