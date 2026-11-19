<?php

namespace App\Support;

use App\Models\HeroImage;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Site-wide look, edited on the Appearance page with a draft and publish flow.
 * The public site reads the published values; a super admin can preview the draft
 * by adding ?preview=draft to a public address.
 *
 * Keys in site_settings: theme.colours, brand.logo, brand.favicon, home.hero_mode, home.hero, map.markers.
 */
final class Appearance
{
    public const COLOUR_NAMES = ['brand' => 'Brand', 'button' => 'Buttons', 'background' => 'Page background', 'band' => 'Section band', 'text' => 'Text'];

    public static function isPreview(): bool
    {
        return request()->query('preview') === 'draft' && (auth()->user()?->isSuperAdmin() ?? false);
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key, ?bool $draft = null): ?array
    {
        $draft ??= self::isPreview();
        $row = SiteSetting::find($key);

        return $draft ? ($row?->draft_value ?? $row?->value) : $row?->value;
    }

    /** @return array<string, string> */
    public static function colours(?bool $draft = null): array
    {
        $colours = config('site.default_colours');
        foreach (self::get('theme.colours', $draft) ?? [] as $name => $hex) {
            if (array_key_exists($name, $colours) && is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                $colours[$name] = strtolower($hex);
            }
        }

        return $colours;
    }

    /** @return array{url: string, alt: string}|null */
    public static function logo(?bool $draft = null): ?array
    {
        $logo = self::get('brand.logo', $draft);

        return ! empty($logo['path']) ? ['url' => Storage::disk('public')->url($logo['path']), 'alt' => (string) ($logo['alt'] ?? 'Just Island Holidays')] : null;
    }

    /** @return array<int, string> size => url */
    public static function favicons(?bool $draft = null): array
    {
        $icon = self::get('brand.favicon', $draft);

        return collect($icon['sizes'] ?? [])->mapWithKeys(fn ($path, $size) => [(int) $size => Storage::disk('public')->url($path)])->all();
    }

    /**
     * Map markers per kind (affiliate, free, paid): label, colour and an optional icon.
     *
     * @return array<string, array{label: string, colour: string, icon: ?string, icon_url: ?string}>
     */
    public static function markers(?bool $draft = null): array
    {
        $saved = self::get('map.markers', $draft) ?? [];
        $markers = [];
        foreach (config('site.map.markers') as $kind => $default) {
            $s = is_array($saved[$kind] ?? null) ? $saved[$kind] : [];
            $colour = is_string($s['colour'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $s['colour']) ? strtolower($s['colour']) : $default['colour'];
            $icon = is_string($s['icon'] ?? null) && $s['icon'] !== '' ? $s['icon'] : null;
            $markers[$kind] = [
                'label' => is_string($s['label'] ?? null) && trim($s['label']) !== '' ? $s['label'] : $default['label'],
                'colour' => $colour,
                'icon' => $icon,
                'icon_url' => $icon ? Storage::disk('public')->url($icon) : null,
            ];
        }

        return $markers;
    }

    /** White price text on each marker colour must reach 4.5 to 1. */
    public static function canPublishMarkers(array $markers): bool
    {
        return collect($markers)->every(fn ($m) => self::contrast('#ffffff', $m['colour']) >= 4.5);
    }

    public static function heroMode(?bool $draft = null): string
    {
        return (self::get('home.hero_mode', $draft)['mode'] ?? 'random') === 'first' ? 'first' : 'random';
    }

    /** The hero images visitors see (published), or the draft arrangement in preview. */
    public static function heroes(?bool $draft = null): Collection
    {
        $draft ??= self::isPreview();
        $arrangement = $draft ? (SiteSetting::find('home.hero')?->draft_value['items'] ?? null) : null;

        if ($arrangement === null) {
            return HeroImage::where('is_active', true)->orderBy('sort_order')->limit(5)->get();
        }

        $active = collect($arrangement)->where('active', true)->sortBy('sort')->pluck('id')->all();

        return HeroImage::whereIn('id', $active)->get()->sortBy(fn ($h) => array_search($h->id, $active))->values();
    }

    public static function heroSrcset(HeroImage $hero): string
    {
        if (! str_ends_with($hero->image_path, '-2400.webp')) {
            return '';
        }

        return collect([800, 1600, 2400])
            ->map(fn ($w) => Storage::disk('public')->url(str_replace('-2400.webp', "-{$w}.webp", $hero->image_path))." {$w}w")
            ->implode(', ');
    }

    /** WCAG 2.x contrast ratio between two hex colours. */
    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    /**
     * The checks shown beside each colour. Text on the background, and white button
     * text on the button colour, must reach 4.5 to 1 or publishing is blocked.
     *
     * @return array<string, array{ratio: float, against: string, required: bool, passes: bool}>
     */
    public static function contrastChecks(array $colours): array
    {
        $check = fn ($fg, $bg, $against, $required) => [
            'ratio' => $r = self::contrast($fg, $bg), 'against' => $against, 'required' => $required, 'passes' => $r >= 4.5,
        ];

        return [
            'text' => $check($colours['text'], $colours['background'], 'page background', true),
            'button' => $check('#ffffff', $colours['button'], 'white button text', true),
            'brand' => $check($colours['brand'], '#ffffff', 'white header', false),
            'band' => $check($colours['text'], $colours['band'], 'text on the band', false),
            'background' => $check($colours['text'], $colours['background'], 'text', false),
        ];
    }

    public static function canPublishColours(array $colours): bool
    {
        return collect(self::contrastChecks($colours))->every(fn ($c) => ! $c['required'] || $c['passes']);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
        $linear = array_map(fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
