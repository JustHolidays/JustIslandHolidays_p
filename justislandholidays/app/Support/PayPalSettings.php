<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Just Island Holidays' own PayPal details, set by super admins on PayPal Settings (20 Nov 2026).
 *
 * Saved in site_settings under "paypal". The client secret is encrypted with the app key
 * (APP_KEY) and never shown again; a field left empty on the page falls back to the server's
 * .env. On every request the saved values are laid over config('services.paypal'), so
 * PayPalClient, the webhook check and launch:check all use them without knowing where
 * they came from.
 */
final class PayPalSettings
{
    public const KEY = 'paypal';

    private const CACHE_KEY = 'settings.paypal';

    public const FIELDS = ['mode', 'client_id', 'client_secret', 'webhook_id', 'brand_name'];

    /** Lays the saved values over config('services.paypal'). Safe before the tables exist. */
    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::find(self::KEY)?->value ?? []);
        } catch (\Throwable) {
            return;   // fresh install or migrations running: .env only
        }

        foreach (self::FIELDS as $field) {
            $value = $saved[$field] ?? null;
            if ($field === 'client_secret' && filled($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable) {
                    $value = null;   // APP_KEY changed: fall back to .env, and the page says so
                }
            }
            if (filled($value)) {
                config(["services.paypal.{$field}" => $value]);
            }
        }
    }

    /** What is saved on the page (secret still encrypted), for showing on the page. */
    public static function saved(): array
    {
        return SiteSetting::find(self::KEY)?->value ?? [];
    }

    /** Last four characters of the secret in use, for "ending …1234". Null when none. */
    public static function secretHint(): ?string
    {
        $secret = (string) config('services.paypal.client_secret');

        return $secret === '' ? null : mb_substr($secret, -4);
    }

    /**
     * Saves the values (secret encrypted). Empty fields fall back to .env. Clears the cache and
     * PayPal sign-in tokens so the next payment uses the new details.
     *
     * @param  array{mode: string, client_id: ?string, client_secret: ?string, webhook_id: ?string, brand_name: ?string}  $values
     */
    public static function save(array $values, ?int $by): void
    {
        $values['client_secret'] = filled($values['client_secret'] ?? null) ? Crypt::encryptString($values['client_secret']) : null;

        SiteSetting::updateOrCreate(['setting_key' => self::KEY], ['value' => $values, 'draft_value' => null, 'updated_by' => $by]);
        Cache::forget(self::CACHE_KEY);
        foreach (['sandbox', 'live'] as $mode) {
            Cache::forget('paypal.token.'.$mode.'.'.md5((string) ($values['client_id'] ?? '')));
        }
        Cache::forget('paypal.token.'.config('services.paypal.mode').'.'.md5((string) config('services.paypal.client_id')));
    }

    /** Asks PayPal for a sign-in token with these details. Null when PayPal accepts them, else why not. */
    public static function check(string $mode, string $clientId, string $secret): ?string
    {
        $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        try {
            $response = Http::baseUrl($base)->withBasicAuth($clientId, $secret)->asForm()->timeout(15)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (\Throwable) {
            return 'PayPal could not be reached. Try again in a minute.';
        }

        if ($response->successful() && filled($response->json('access_token'))) {
            return null;
        }

        return $response->status() === 401
            ? 'PayPal did not accept this client ID and secret for '.($mode === 'live' ? 'live' : 'sandbox').' mode. Check both, and that they are the '.($mode === 'live' ? 'Live' : 'Sandbox').' app\'s details.'
            : 'PayPal answered with an error ('.$response->status().'). Nothing was saved.';
    }
}
