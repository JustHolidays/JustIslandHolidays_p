@extends('layouts.account', ['title' => 'Two-factor sign-in'])

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Two-factor sign-in</h1>

    @if ($user->isAdmin())
        <p class="mb-6">Admin accounts must use two-factor sign-in. You need an authenticator app on your phone, such as Google Authenticator or Microsoft Authenticator.</p>
    @else
        <p class="mb-6">Two-factor sign-in adds a code from your phone each time you sign in. It is optional for your account.</p>
    @endif

    @if ($user->hasConfirmedTwoFactor())
        <p role="status" class="mb-6 rounded border border-green-300 bg-green-50 p-4">Two-factor sign-in is on.</p>

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

        @unless ($user->isAdmin())
            <form method="POST" action="{{ route('two-factor.disable') }}">
                @csrf
                @method('DELETE')
                <x-button>Turn off two-factor sign-in</x-button>
            </form>
        @endunless
    @elseif ($user->two_factor_secret)
        <h2 class="mb-2 text-lg font-semibold">Step 2: scan the code</h2>
        <p class="mb-4">Scan this with your authenticator app, then enter the 6-digit code it shows.</p>
        <div class="mb-4 inline-block bg-white p-4" role="img" aria-label="QR code for your authenticator app">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <p class="mb-6 text-sm">Cannot scan it? Enter this key in the app instead: <span class="font-mono">{{ decrypt($user->two_factor_secret) }}</span></p>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="max-w-md">
            @csrf
            <x-field name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" required />
            <x-button>Turn on two-factor sign-in</x-button>
        </form>
    @else
        <h2 class="mb-2 text-lg font-semibold">Step 1: start set-up</h2>
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <x-button>Set up two-factor sign-in</x-button>
        </form>
    @endif
@endsection
