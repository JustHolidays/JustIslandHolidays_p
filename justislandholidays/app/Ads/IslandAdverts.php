<?php

namespace App\Ads;

use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Island;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Affiliate adverts (decided 19 Nov 2026). Every advert belongs to one island and is shown in
 * one or both sections, chosen by super admins:
 *  - Menu Advert Section: 4 banners below the introduction on the island page and on every
 *    resort page of that island. With more than 4 running, 4 are picked at random (weighted
 *    by `weight`) on each page view; with fewer, the rest are placeholders.
 *  - Sidebar Section: on property pages on that island, below Check availability. Every
 *    running advert is shown, in random order.
 *  - Both: the advert appears in both sections.
 * Each section is an ad slot (ad_slots.code), so no extra column is needed.
 */
final class IslandAdverts
{
    /** Section => ad_slots.code */
    public const SECTIONS = ['menu' => 'menu_section', 'sidebar' => 'sidebar_section', 'both' => 'both_sections'];

    public const SECTION_LABELS = [
        'menu' => 'Menu Advert Section',
        'sidebar' => 'Sidebar Section',
        'both' => 'Both',
    ];

    /** Banners in the Menu Advert Section. */
    public const MENU_COUNT = 4;

    /** Shown 75 pixels tall (decided 19 Nov 2026): a 4 to 1 banner. */
    public const WIDTH = 300;

    public const HEIGHT = 75;

    /** @return list<Ad|null> four banners for the island and resort pages; null shows the placeholder */
    public static function menuFor(Island $island): array
    {
        $ads = self::pickWeighted(self::running($island, ['menu', 'both'])->all(), self::MENU_COUNT);

        return array_pad($ads, self::MENU_COUNT, null);
    }

    /** @return list<Ad> every running sidebar advert for property pages on this island, in random order */
    public static function sidebarFor(Island $island): array
    {
        return self::running($island, ['sidebar', 'both'])->shuffle()->values()->all();
    }

    /** Which section an advert is in, from its slot. */
    public static function sectionOf(Ad $ad): ?string
    {
        $code = $ad->slot?->code;

        return $code ? (array_search($code, self::SECTIONS, true) ?: null) : null;
    }

    /** @param  list<string>  $sections */
    private static function running(Island $island, array $sections)
    {
        $slotIds = AdSlot::whereIn('code', array_map(fn ($s) => self::SECTIONS[$s], $sections))->pluck('id');

        return Ad::where('island_id', $island->id)
            ->whereIn('slot_id', $slotIds)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->get();
    }

    /**
     * Up to $count adverts picked at random without repeats, each draw weighted by `weight`,
     * so an advert with weight 2 is twice as likely to be picked as one with weight 1.
     *
     * @param  list<Ad>  $ads
     * @return list<Ad>
     */
    private static function pickWeighted(array $ads, int $count): array
    {
        $picked = [];
        while ($ads !== [] && count($picked) < $count) {
            $total = array_sum(array_map(fn (Ad $a) => max(1, (int) $a->weight), $ads));
            $roll = random_int(1, $total);
            foreach ($ads as $i => $ad) {
                $roll -= max(1, (int) $ad->weight);
                if ($roll <= 0) {
                    $picked[] = $ad;
                    unset($ads[$i]);
                    $ads = array_values($ads);
                    break;
                }
            }
        }

        return $picked;
    }

    public static function isRunning(Ad $ad): bool
    {
        return $ad->status === 'active' && $ad->starts_at <= now() && $ad->ends_at > now();
    }

    /** Counts a click for today in ad_stats_daily (works on MySQL and SQLite). */
    public static function countClick(Ad $ad): void
    {
        $key = ['ad_id' => $ad->id, 'stat_date' => now()->toDateString()];
        DB::table('ad_stats_daily')->insertOrIgnore($key + ['impressions' => 0, 'clicks' => 0]);
        DB::table('ad_stats_daily')->where($key)->increment('clicks');
    }

    // --- Images ------------------------------------------------------------------------

    /**
     * Crops to 4:1 from the centre and saves WebP copies 300 wide and, when the source is
     * big enough, 600 wide for sharp screens. Returns the 300 path.
     */
    public static function storeImage(UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src instanceof GdImage) {
            throw ValidationException::withMessages(['image' => 'The image could not be read. Try a JPG, PNG or WebP file.']);
        }

        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $cropW = min($sw, $sh * 4);
        $cropH = (int) floor($cropW / 4);
        $x = (int) (($sw - $cropW) / 2);
        $y = (int) (($sh - $cropH) / 2);

        $base = 'ads/'.Str::uuid();
        foreach ([self::WIDTH, self::WIDTH * 2] as $w) {
            if ($w > $cropW && $w !== self::WIDTH) {
                break;   // never upscale for the sharp-screen copy
            }
            $h = (int) ($w / 4);
            $copy = imagecreatetruecolor($w, $h);
            imagecopyresampled($copy, $src, 0, 0, $x, $y, $w, $h, $cropW, $cropH);
            ob_start();
            imagewebp($copy, null, 85);
            Storage::disk('public')->put("{$base}-{$w}.webp", (string) ob_get_clean(), 'public');
        }

        return "{$base}-".self::WIDTH.'.webp';
    }

    public static function deleteImage(?string $path): void
    {
        if ($path && preg_match('#^(ads/[0-9a-f-]+)-\d+\.webp$#', $path, $m)) {
            Storage::disk('public')->delete(["{$m[1]}-".self::WIDTH.'.webp', "{$m[1]}-".(self::WIDTH * 2).'.webp']);
        }
    }

    public static function imageUrl(Ad $ad): string
    {
        return Storage::disk('public')->url($ad->image_path);
    }

    /** 1x and 2x copies, so the 75-pixel banner stays sharp on high-density screens. */
    public static function imageSrcset(Ad $ad): string
    {
        $large = str_replace('-'.self::WIDTH.'.webp', '-'.(self::WIDTH * 2).'.webp', $ad->image_path);
        $set = [Storage::disk('public')->url($ad->image_path).' 1x'];
        if (Storage::disk('public')->exists($large)) {
            $set[] = Storage::disk('public')->url($large).' 2x';
        }

        return implode(', ', $set);
    }

    public static function placeholderUrl(): string
    {
        return asset('images/placeholders/advert-300.webp');
    }

    public static function placeholderSrcset(): string
    {
        return asset('images/placeholders/advert-300.webp').' 1x, '.asset('images/placeholders/advert-600.webp').' 2x';
    }
}
