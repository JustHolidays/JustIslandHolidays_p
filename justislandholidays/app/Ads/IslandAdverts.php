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
 * The four advert columns below the introduction on island pages. Every advert belongs
 * to one island (no "all islands", decided 19 Nov 2026), and only super admins manage
 * them. A column with no running advert shows the placeholder.
 *
 * Several adverts may share a column on the same island: one is picked at random,
 * weighted by `weight`, as the spec describes.
 */
final class IslandAdverts
{
    /** Column number => ad_slots.code */
    public const SLOTS = [1 => 'island_intro_1', 2 => 'island_intro_2', 3 => 'island_intro_3', 4 => 'island_intro_4'];

    /** Shown 75 pixels tall (decided 19 Nov 2026) across a quarter of the page: a 4 to 1 banner. */
    public const WIDTH = 300;

    public const HEIGHT = 75;

    /** @return array<int, Ad|null> column number => advert to show, or null for the placeholder */
    public static function for(Island $island): array
    {
        $slots = AdSlot::whereIn('code', self::SLOTS)->pluck('id', 'code');

        $running = Ad::where('island_id', $island->id)
            ->whereIn('slot_id', $slots->values())
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->get()
            ->groupBy('slot_id');

        $columns = [];
        foreach (self::SLOTS as $position => $code) {
            $columns[$position] = self::pickWeighted($running->get($slots[$code] ?? 0, collect())->all());
        }

        return $columns;
    }

    /** @param  list<Ad>  $ads */
    private static function pickWeighted(array $ads): ?Ad
    {
        if ($ads === []) {
            return null;
        }
        $total = array_sum(array_map(fn (Ad $a) => max(1, (int) $a->weight), $ads));
        $roll = random_int(1, $total);
        foreach ($ads as $ad) {
            $roll -= max(1, (int) $ad->weight);
            if ($roll <= 0) {
                return $ad;
            }
        }

        return $ads[0];
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
