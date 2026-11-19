@extends('layouts.account', ['title' => 'Two-factor sign-in'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Two-factor sign-in</h1>

    @if ($user->isAdmin())
        <p class="mb-2">Admin accounts always need a second step when signing in. You can choose how:</p>
        <ul class="mb-6 list-disc pl-5">
            <li><strong>A code by email</strong> (the default): we email you a 6-digit code each time you sign in. It works once, for 10 minutes.</li>
            <li><strong>An authenticator app</strong> (stronger): a code from an app on your phone, such as Google Authenticator or Microsoft Authenticator. Safer, because someone who gets into your email still cannot sign in.</li>
        </ul>
        <p role="status" class="mb-6 rounded border border-slate-300 bg-white p-4">
            You are using: <strong>{{ $user->hasConfirmedTwoFactor() ? 'an authenticator app' : 'a code by email' }}</strong>.
        </p>
    @else
        <p class="mb-6">Two-factor sign-in adds a code from your phone each time you sign in. It is optional for your account.</p>
    @endif

    @if ($user->hasConfirmedTwoFactor())
        <p role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">Authenticator app sign-in is on.</p>

        <form method="GET" action="{{ route('account.security') }}" class="mb-6">
            <input type="hidden" name="show" value="codes">
            <x-button>Show recovery codes</x-button>
        </form>

        @if (request('show') === 'codes')
            <h2 class="mb-2 text-lg font-semibold">Recovery codes</h2>
            <p class="mb-2">Keep these somewhere safe. Each one works once if you lose your phone.</p>
            <ul class="mb-6 rounded border border-slate-300 bg-white p-4 font-mono">
                @foreach ($user->recoveryCodes() as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('two-factor.disable') }}">
            @csrf
            @method('DELETE')
            <x-button>{{ $user->isAdmin() ? 'Stop using the app and get codes by email instead' : 'Turn off two-factor sign-in' }}</x-button>
        </form>
    @elseif ($user->two_factor_secret)
        <h2 class="mb-2 text-lg font-semibold">Step 2: scan the code</h2>
        <p class="mb-4">Scan this with your authenticator app, then enter the 6-digit code it shows.</p>
        <div class="mb-4 inline-block bg-white p-4" role="img" aria-label="QR code for your authenticator app">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <p class="mb-6 text-sm">Cannot scan it? Enter this key in the app instead: <span class="font-mono">{{ decrypt($user->two_factor_secret) }}</span></p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="max-w-md">
            @csrf
            <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" required />
            <x-button>Turn on authenticator app sign-in</x-button>
        </form>
    @else
        <h2 class="mb-2 text-lg font-semibold">{{ $user->isAdmin() ? 'Use an authenticator app instead' : 'Step 1: start set-up' }}</h2>
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <x-button>Set up an authenticator app</x-button>
        </form>
    @endif
@endsection
