<?php

namespace App\Support;

use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Maintenance mode (20 Nov 2026), switched on by super admins on Settings › Maintenance mode.
 *
 * While it is on, visitors see a maintenance page instead of the public site (home, islands,
 * resorts, search, properties, content pages, partners, List your property, enquiries and
 * favourites), with HTTP 503 so search engines know it is temporary. Signed-in admins see and
 * use the site as normal, with a banner. Sign-in, accounts, the admin area, payments, webhooks
 * and calendars embedded on owners' own websites keep working.
 */
final class Maintenance
{
    public const KEY = 'site.maintenance';

    private const CACHE_KEY = 'settings.maintenance';

    /** Public routes the maintenance page replaces. */
    public const PUBLIC_ROUTES = ['home', 'search', 'owners', 'partners', 'pages.show', 'enquiries.store', 'islands.show', 'areas.show', 'listings.show', 'favourites.*'];

    public const DEFAULT_TITLE = 'We are making some improvements';

    public const DEFAULT_MESSAGE = "Just Island Holidays is having a little holiday of its own while we polish things up. We will be back very soon with holiday homes across Mallorca and Menorca.\n\nThank you for your patience.";

    /** @return array{on: bool, title: string, message: string, until: ?CarbonImmutable, started_at: ?string} */
    public static function settings(): array
    {
        try {
            $value = Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::find(self::KEY)?->value ?? []);
        } catch (\Throwable) {
            $value = [];   // before the tables exist
        }

        return [
            'on' => (bool) ($value['on'] ?? false),
            'title' => (string) (($value['title'] ?? '') ?: self::DEFAULT_TITLE),
            'message' => (string) (($value['message'] ?? '') ?: self::DEFAULT_MESSAGE),
            'until' => filled($value['until'] ?? null) ? CarbonImmutable::parse($value['until']) : null,
            'started_at' => $value['started_at'] ?? null,
        ];
    }

    public static function isOn(): bool
    {
        return self::settings()['on'];
    }

    /** @param  array{on: bool, title: ?string, message: ?string, until: ?string}  $values */
    public static function save(array $values, ?int $by): void
    {
        $was = self::settings();
        SiteSetting::updateOrCreate(['setting_key' => self::KEY], ['value' => [
            'on' => $values['on'],
            'title' => trim((string) $values['title']) ?: null,
            'message' => trim((string) $values['message']) ?: null,
            'until' => $values['until'] ?: null,
            'started_at' => $values['on'] ? ($was['on'] ? $was['started_at'] : now()->toDateTimeString()) : null,
        ], 'draft_value' => null, 'updated_by' => $by]);
        Cache::forget(self::CACHE_KEY);
    }
}
