<?php

namespace App\Support;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The links in the public footer (21 Nov 2026), edited by super admins on Settings › Footer links.
 *
 * Each link has a label (per language) and goes to a site-wide content page, a built-in page or
 * another website (https). Until links are saved, the standard set is used. A content page that
 * is missing or not published is left out of the footer rather than linking to a page that is
 * not there.
 */
final class FooterLinks
{
    public const KEY = 'footer.links';

    private const CACHE_KEY = 'settings.footer_links';

    public const MAX_LINKS = 12;

    /** Built-in pages a link can go to: key => [route name, standard label]. */
    public const BUILT_IN = [
        'home' => ['home', 'Home'],
        'search' => ['search', 'Search'],
        'partners' => ['partners', 'Our partners'],
        'owners' => ['owners', 'For owners'],
        'login' => ['login', 'Owner sign in'],
    ];

    /** @return list<array{type: string, target: string, label: array<string, string>}> */
    public static function defaults(): array
    {
        $page = fn (string $slug, string $label) => ['type' => 'page', 'target' => $slug, 'label' => ['en' => $label]];
        $builtIn = fn (string $key) => ['type' => 'builtin', 'target' => $key, 'label' => ['en' => self::BUILT_IN[$key][1]]];

        return [
            $page('about', 'About us'), $page('contact', 'Contact'), $page('terms', 'Terms'), $page('privacy', 'Privacy'), $page('accessibility', 'Accessibility'),
            $builtIn('partners'), $builtIn('owners'), $builtIn('login'),
        ];
    }

    /** The saved links, or the standard ones. @return list<array{type: string, target: string, label: array<string, string>}> */
    public static function saved(): array
    {
        try {
            $value = Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::find(self::KEY)?->value);
        } catch (\Throwable) {
            $value = null;   // before the tables exist
        }

        return is_array($value) ? array_values($value) : self::defaults();
    }

    public static function isCustomised(): bool
    {
        return SiteSetting::find(self::KEY)?->value !== null;
    }

    /**
     * What the footer shows, in order.
     *
     * @return list<array{label: string, url: string, external: bool}>
     */
    public static function forFooter(string $lang = 'en'): array
    {
        $links = self::saved();
        $slugs = collect($links)->where('type', 'page')->pluck('target')->all();
        $published = $slugs === [] ? [] : rescue(fn () => Page::whereNull('island_id')->where('is_published', true)->whereIn('slug', $slugs)->pluck('slug')->all(), [], false);

        $out = [];
        foreach ($links as $link) {
            $label = trim((string) ($link['label'][$lang] ?? '')) ?: __(trim((string) ($link['label']['en'] ?? '')));
            if ($label === '') {
                continue;
            }
            $url = match ($link['type'] ?? null) {
                'page' => in_array($link['target'], $published, true) ? route('pages.show', ['lang' => $lang, 'slug' => $link['target']]) : null,
                'builtin' => isset(self::BUILT_IN[$link['target']]) ? self::builtInUrl(self::BUILT_IN[$link['target']][0], $lang) : null,
                'url' => str_starts_with((string) $link['target'], 'https://') ? $link['target'] : null,
                default => null,
            };
            if ($url !== null) {
                $out[] = ['label' => $label, 'url' => $url, 'external' => $link['type'] === 'url'];
            }
        }

        return $out;
    }

    /** A built-in page's address, with the language where the page has one (works in the admin too). */
    private static function builtInUrl(string $name, string $lang): string
    {
        $route = app('router')->getRoutes()->getByName($name);

        return route($name, $route && in_array('lang', $route->parameterNames(), true) ? ['lang' => $lang] : []);
    }

    /** @param  list<array{type: string, target: string, label: array<string, string>}>|null  $links  null puts back the standard links */
    public static function save(?array $links, ?int $by): void
    {
        if ($links === null) {
            SiteSetting::where('setting_key', self::KEY)->delete();
        } else {
            SiteSetting::updateOrCreate(['setting_key' => self::KEY], ['value' => array_values($links), 'draft_value' => null, 'updated_by' => $by]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
