<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Colours of the admin area (header bar, page background, menu highlight),  set by super
 * admins on the Admin Styler page. Stored in site_settings 'admin.colours' and applied at
 * once (no draft: it only affects staff). Saving is blocked if any text would fall below
 * 4.5 to 1 contrast, so the admin area can never be made unreadable.
 */
final class AdminTheme
{
    public const KEY = 'admin.colours';

    /** The text colour used on the page background and on the menu highlight. */
    public const PAGE_TEXT = '#0f172a';

    public const DEFAULTS = [
        'header' => '#0f172a',       // header bar
        'header_text' => '#ffffff',  // text and links in the header bar
        'page' => '#f1f5f9',         // page background
        'highlight' => '#ffffff',    // current and hovered menu item
    ];

    public const LABELS = [
        'header' => 'Header bar',
        'header_text' => 'Header text',
        'page' => 'Page background',
        'highlight' => 'Menu highlight',
    ];

    /** @return array<string, string> */
    public static function colours(): array
    {
        $colours = self::DEFAULTS;
        foreach (SiteSetting::find(self::KEY)?->value ?? [] as $name => $hex) {
            if (array_key_exists($name, $colours) && is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                $colours[$name] = strtolower($hex);
            }
        }

        return $colours;
    }

    /** @return array<string, array{ratio: float, against: string, passes: bool}> */
    public static function checks(array $colours): array
    {
        $check = fn ($a, $b, $against) => ['ratio' => $r = Appearance::contrast($a, $b), 'against' => $against, 'passes' => $r >= 4.5];

        return [
            'header' => $check($colours['header_text'], $colours['header'], 'header text'),
            'header_text' => $check($colours['header_text'], $colours['header'], 'header bar'),
            'page' => $check(self::PAGE_TEXT, $colours['page'], 'dark page text'),
            'highlight' => $check(self::PAGE_TEXT, $colours['highlight'], 'dark menu text'),
        ];
    }

    public static function canSave(array $colours): bool
    {
        return collect(self::checks($colours))->every(fn ($c) => $c['passes']);
    }

    /** CSS custom properties for the admin layout. Values are checked hex codes only. */
    public static function css(): string
    {
        $c = self::colours();

        return ":root { --admin-header: {$c['header']}; --admin-header-text: {$c['header_text']}; --admin-page: {$c['page']}; --admin-highlight: {$c['highlight']}; }";
    }
}
