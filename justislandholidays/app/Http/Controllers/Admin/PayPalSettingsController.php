<?php

namespace App\Http\Controllers\Admin;

use App\Support\Audit;
use App\Support\PayPalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * PayPal Settings (super admins only, 20 Nov 2026): Just Island Holidays' PayPal details.
 * The client ID and secret are checked with PayPal before anything is saved, so a typing
 * mistake cannot stop payments. The secret is encrypted and never shown again.
 */
class PayPalSettingsController extends AdminController
{
    public function edit(): View
    {
        $saved = PayPalSettings::saved();

        return view('admin.paypal-settings', [
            'paypal' => config('services.paypal'),
            'saved' => $saved,
            'secretHint' => PayPalSettings::secretHint(),
            'secretFromPage' => filled($saved['client_secret'] ?? null),
            'secretUnreadable' => filled($saved['client_secret'] ?? null) && ! $this->decrypts($saved['client_secret']),
            'webhookUrl' => route('webhooks.paypal'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['sandbox', 'live'])],
            'client_id' => ['nullable', 'string', 'max:200'],
            'client_secret' => ['nullable', 'string', 'max:200'],
            'webhook_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-]+$/'],
            'brand_name' => ['nullable', 'string', 'max:127'],
            'live_confirm' => ['nullable', 'boolean'],
        ], [
            'webhook_id.regex' => 'The webhook ID has only letters, numbers and dashes, for example 1AB23456CD789012E.',
        ]);

        $saved = PayPalSettings::saved();
        $secretKept = blank($data['client_secret'] ?? null);
        // A blank secret keeps the one in use (from this page, else .env).
        $secret = $secretKept ? (string) config('services.paypal.client_secret') : trim($data['client_secret']);
        $clientId = trim((string) ($data['client_id'] ?? '')) ?: (string) config('services.paypal.client_id');

        if ($data['mode'] === 'live' && config('services.paypal.mode') !== 'live' && ! $request->boolean('live_confirm')) {
            throw ValidationException::withMessages(['live_confirm' => 'Tick the box to confirm switching to live payments.']);
        }

        // Check the ID and secret with PayPal first: nothing is saved if PayPal refuses them.
        if ($clientId !== '' || $secret !== '') {
            if ($clientId === '' || $secret === '') {
                throw ValidationException::withMessages(['client_id' => 'Add both the client ID and the secret.']);
            }
            if ($why = PayPalSettings::check($data['mode'], $clientId, $secret)) {
                throw ValidationException::withMessages(['client_id' => $why]);
            }
        }

        $values = [
            'mode' => $data['mode'],
            'client_id' => trim((string) ($data['client_id'] ?? '')) ?: null,
            // Keep the saved secret when the box is left empty; .env otherwise.
            'client_secret' => $secretKept ? ($this->decrypts($saved['client_secret'] ?? null) ?: null) : $secret,
            'webhook_id' => trim((string) ($data['webhook_id'] ?? '')) ?: null,
            'brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: null,
        ];
        $before = config('services.paypal');
        PayPalSettings::save($values, $request->user()->id);
        PayPalSettings::apply();

        // Which details changed, never their values.
        $changed = collect(PayPalSettings::FIELDS)
            ->filter(fn ($f) => $f === 'client_secret' ? ! $secretKept : (string) ($before[$f] ?? '') !== (string) config("services.paypal.{$f}"))
            ->values()->all();
        Audit::record($request->user(), 'settings.paypal', 'site_setting', PayPalSettings::KEY, [
            'changed' => $changed, 'mode' => $data['mode'],
        ]);

        return back()->with('status', 'PayPal details saved'.($clientId !== '' ? ' and checked with PayPal' : '').'. '
            .($data['mode'] === 'live' ? 'Payments are live.' : 'Payments use the PayPal sandbox (test money only).'));
    }

    private function decrypts(?string $encrypted): ?string
    {
        if (blank($encrypted)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
