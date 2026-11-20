<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SEO wording and sharing (20 Nov 2026), set by super admins on Settings › SEO.
 *
 * Titles and descriptions: each page passes its keys (most specific first, for example
 * ['island:5', 'island']) and the wording built into the site. A saved title or description
 * for the first key that has one is used instead, with its {placeholders} filled in. Saved
 * wording is cached and cleared on every save.
 */
final class Seo
{
    /**
     * Page types: label, the placeholders they can use, and the wording built into the site
     * (in English, shown greyed in the empty boxes). Keep in step with the public views.
     */
    public const PAGES = [
        'home' => ['label' => 'Home page', 'vars' => [],
            'title' => 'Holiday homes in Mallorca and Menorca | Just Island Holidays',
            'description' => 'Find villas, apartments and fincas in Mallorca and Menorca, and contact the owners directly.'],
        'island' => ['label' => 'Island pages', 'vars' => ['island', 'count'],
            'title' => 'Holiday homes in {island} | Just Island Holidays',
            'description' => 'Villas, apartments and fincas to rent in {island}. Contact the owners directly.'],
        'area' => ['label' => 'Resort and town pages', 'vars' => ['resort', 'island', 'count'],
            'title' => 'Holiday homes in {resort} | Just Island Holidays',
            'description' => 'Villas, apartments and fincas to rent in {resort}. Contact the owners directly.'],
        'search' => ['label' => 'Search', 'vars' => [],
            'title' => 'Search holiday homes | Just Island Holidays',
            'description' => ''],
        'owners' => ['label' => 'List your property', 'vars' => [],
            'title' => 'List your holiday home | Just Island Holidays',
            'description' => 'Advertise your holiday home in Mallorca or Menorca. Guests contact you directly, with no booking fees.'],
        'partners' => ['label' => 'Our partners', 'vars' => [],
            'title' => 'Our partners | Just Island Holidays',
            'description' => 'The booking partners whose holiday homes you can find on Just Island Holidays.'],
    ];

    /** The page type of a key: "island:5" is an island page. */
    public static function type(string $key): string
    {
        return explode(':', $key)[0];
    }

    /** Roughly what search engines show before cutting text off. */
    public const TITLE_LENGTH = 60;

    public const DESCRIPTION_LENGTH = 155;

    private const CACHE_KEY = 'seo.meta.';

    private const SHARE_KEY = 'seo.share';

    /**
     * The title and description to use for a page.
     *
     * @param  string|list<string>|null  $keys  most specific first
     * @param  array<string, string|int>  $vars  placeholder values, for example ['island' => 'Menorca']
     * @return array{0: ?string, 1: ?string}
     */
    public static function resolve(string|array|null $keys, array $vars, ?string $title, ?string $description, ?string $lang = null): array
    {
        if ($keys === null) {
            return [$title, $description];
        }
        $saved = self::all($lang ?? app()->getLocale());
        foreach ((array) $keys as $key) {
            if (filled($saved[$key]['title'] ?? null)) {
                $title = self::fill($saved[$key]['title'], $vars);
                break;
            }
        }
        foreach ((array) $keys as $key) {
            if (filled($saved[$key]['description'] ?? null)) {
                $description = self::fill($saved[$key]['description'], $vars);
                break;
            }
        }

        return [$title, $description];
    }

    /** @return array<string, array{title: ?string, description: ?string}> saved wording for a language */
    public static function all(string $lang): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY.$lang, fn () => DB::table('seo_meta')->where('lang', $lang)->get()
                ->mapWithKeys(fn ($r) => [$r->page_key => ['title' => $r->title, 'description' => $r->description]])->all());
        } catch (\Throwable) {
            return [];   // before the table exists
        }
    }

    public static function fill(string $text, array $vars): string
    {
        return trim(preg_replace('/\{(\w+)\}/', '', strtr($text, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all())));
    }

    /** Saves one page's wording; both empty removes it (the built-in wording is used again). */
    public static function save(string $key, string $lang, ?string $title, ?string $description, ?int $by): void
    {
        $title = trim((string) $title) ?: null;
        $description = trim((string) $description) ?: null;
        if ($title === null && $description === null) {
            DB::table('seo_meta')->where(['page_key' => $key, 'lang' => $lang])->delete();
        } else {
            DB::table('seo_meta')->updateOrInsert(
                ['page_key' => $key, 'lang' => $lang],
                ['title' => $title, 'description' => $description, 'updated_by' => $by, 'updated_at' => now(), 'created_at' => now()],
            );
        }
        Cache::forget(self::CACHE_KEY.$lang);
    }

    // --- Sharing --------------------------------------------------------------------------

    /** The default image shown when a page is shared (1200 × 630), or null. */
    public static function shareImageUrl(): ?string
    {
        $path = SiteSetting::find(self::SHARE_KEY)?->value['image_path'] ?? null;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function setShareImage(?string $path, ?int $by): void
    {
        $old = SiteSetting::find(self::SHARE_KEY)?->value['image_path'] ?? null;
        SiteSetting::updateOrCreate(['setting_key' => self::SHARE_KEY], ['value' => ['image_path' => $path], 'draft_value' => null, 'updated_by' => $by]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
    }

    /** Saves an uploaded picture as the 1200 × 630 JPEG that sharing previews use, cropped to fit. */
    public static function storeShareImage(\Illuminate\Http\UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source instanceof \GdImage) {
            throw \Illuminate\Validation\ValidationException::withMessages(['share_image' => 'The image could not be read. Try a JPG or PNG.']);
        }
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = max(1200 / $w, 630 / $h);
        $cropW = (int) round(1200 / $scale);
        $cropH = (int) round(630 / $scale);
        $canvas = imagecreatetruecolor(1200, 630);
        imagecopyresampled($canvas, $source, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), 1200, 630, $cropW, $cropH);
        ob_start();
        imagejpeg($canvas, null, 85);
        $path = 'seo/share-'.\Illuminate\Support\Str::uuid().'.jpg';
        Storage::disk('public')->put($path, (string) ob_get_clean(), 'public');

        return $path;
    }

    /** schema.org Organization for the home page: the business behind the site. */
    public static function organisation(): array
    {
        $b = config('site.business');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $b['name'],
            'url' => rtrim((string) config('app.url'), '/'),
            'logo' => Appearance::logo()['url'] ?? null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $b['street'],
                'addressLocality' => $b['locality'],
                'postalCode' => $b['postcode'],
                'addressCountry' => $b['country'],
            ],
        ]);
    }
}
