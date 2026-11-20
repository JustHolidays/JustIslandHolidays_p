@extends('layouts.admin', ['title' => 'PayPal Settings'])

@section('content')
    <h1 class="text-2xl font-semibold">PayPal Settings</h1>
    <p class="mt-1 max-w-3xl">Only super admins can open this page, and every change is written to the audit log (which details changed, never their values).</p>

    <section class="mt-6 max-w-3xl rounded-lg bg-white p-5 shadow-sm" aria-labelledby="paypal-heading">
        <h2 id="paypal-heading" class="text-lg font-semibold">Account details</h2>
        <p class="mt-1 text-sm">Just Island Holidays' own PayPal account, which owners and agents pay for their plans. Find these in the PayPal developer dashboard, under Apps and credentials, in the app for this website. Use the Sandbox app for testing and the Live app for real payments.</p>

        <dl class="mt-4 grid gap-2 rounded border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-[12rem_1fr]">
            <dt class="font-medium">Payments now</dt>
            <dd data-paypal-mode="{{ $paypal['mode'] ?? 'sandbox' }}">
                @if (($paypal['mode'] ?? 'sandbox') === 'live')
                    <strong class="text-green-800">Live</strong>: real payments.
                @else
                    <strong class="text-amber-800">Sandbox</strong>: test money only.
                @endif
            </dd>
            <dt class="font-medium">Client ID</dt>
            <dd class="break-all">{{ filled($paypal['client_id'] ?? null) ? $paypal['client_id'] : 'Not set' }} <span class="text-slate-600">({{ filled($saved['client_id'] ?? null) ? 'set on this page' : 'from the server' }})</span></dd>
            <dt class="font-medium">Secret</dt>
            <dd>
                @if ($secretHint)
                    Saved, ending …{{ $secretHint }} <span class="text-slate-600">({{ $secretFromPage ? 'set on this page' : 'from the server' }})</span>
                @else
                    Not set
                @endif
                @if ($secretUnreadable)
                    <span class="block font-medium text-red-800">The secret saved here can no longer be read (the server's app key changed). Enter it again.</span>
                @endif
            </dd>
            <dt class="font-medium">Webhook ID</dt>
            <dd class="break-all">{{ filled($paypal['webhook_id'] ?? null) ? $paypal['webhook_id'] : 'Not set: payments cannot be confirmed without it' }}</dd>
            <dt class="font-medium">Webhook address</dt>
            <dd class="break-all font-mono">{{ $webhookUrl }} <span class="block font-sans text-slate-600">Add this address to the PayPal app's webhooks, then copy its webhook ID here.</span></dd>
        </dl>

        <form method="POST" action="{{ route('admin.paypal-settings.update') }}" class="mt-5 space-y-4" autocomplete="off">
            @csrf @method('PUT')
            @error('client_id')<p role="alert" class="rounded border border-red-300 bg-red-50 p-3 font-medium text-red-800">{{ $message }}</p>@enderror

            <fieldset>
                <legend class="mb-1 font-medium">Mode</legend>
                @foreach (['sandbox' => 'Sandbox (test money only)', 'live' => 'Live (real payments)'] as $value => $label)
                    <div class="flex min-h-11 items-center gap-2">
                        <input id="mode_{{ $value }}" name="mode" type="radio" value="{{ $value }}" class="h-5 w-5" @checked(old('mode', $paypal['mode'] ?? 'sandbox') === $value)>
                        <label for="mode_{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </fieldset>

            <x-field name="client_id" label="Client ID" :value="old('client_id', $saved['client_id'] ?? null)" hint="Leave empty to use the one in the server's .env file." autocomplete="off" />

            <div>
                <label for="client_secret" class="mb-1 block font-medium">Secret</label>
                <input id="client_secret" name="client_secret" type="password" autocomplete="new-password" class="block min-h-11 w-full rounded border border-slate-400 px-3" aria-describedby="client_secret-hint">
                <p id="client_secret-hint" class="mt-1 text-sm text-slate-600">Leave empty to keep the secret in use. It is stored encrypted and never shown again.</p>
            </div>

            <x-field name="webhook_id" label="Webhook ID" :value="old('webhook_id', $saved['webhook_id'] ?? null)" hint="Leave empty to use the one in the server's .env file." autocomplete="off" />
            <x-field name="brand_name" label="Name shown on PayPal" :value="old('brand_name', $saved['brand_name'] ?? null)" hint="Shown to owners on PayPal's pages. Leave empty for Just Island Holidays." />

            @if (($paypal['mode'] ?? 'sandbox') !== 'live')
                <div class="flex items-start gap-2 rounded border border-amber-300 bg-amber-50 p-3">
                    <input id="live_confirm" name="live_confirm" type="checkbox" value="1" class="mt-1 h-5 w-5 shrink-0">
                    <label for="live_confirm">If I choose Live, I understand owners will be charged real money. <span class="text-sm text-slate-700">Only needed when switching to Live.</span></label>
                </div>
                @error('live_confirm')<p class="text-sm font-medium text-red-800">{{ $message }}</p>@enderror
            @endif

            <p class="text-sm text-slate-700">When you save, the client ID and secret are checked with PayPal first. If PayPal does not accept them, nothing is saved.</p>
            <x-button>Check with PayPal and save</x-button>
        </form>
    </section>
@endsection
