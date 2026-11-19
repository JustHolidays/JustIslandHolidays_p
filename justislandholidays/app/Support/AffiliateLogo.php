<?php

namespace App\Support;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Affiliate logos for the banner above Check availability (shown 100 pixels tall, the logo
 * fitted inside without cropping). Stored as one WebP at up to 200 pixels tall, so it is
 * sharp on high-density screens. Transparency is kept. No SVG: an SVG served from our
 * domain could carry script.
 */
final class AffiliateLogo
{
    public const HEIGHT = 100;

    public const MIN_HEIGHT = 100;

    public static function store(UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src instanceof GdImage) {
            throw ValidationException::withMessages(['logo' => 'The logo could not be read. Try a PNG, JPG or WebP file.']);
        }

        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $scale = min(1, (self::HEIGHT * 2) / $sh, 704 / $sw);   // fit 704 × 200, never enlarge
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefilledrectangle($out, 0, 0, $w, $h, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

        ob_start();
        imagewebp($out, null, 90);
        $path = 'affiliates/logo-'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, (string) ob_get_clean(), 'public');

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'affiliates/logo-')) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function url(User $affiliate): ?string
    {
        return $affiliate->affiliate_logo_path ? Storage::disk('public')->url($affiliate->affiliate_logo_path) : null;
    }

    public static function placeholderUrl(): string
    {
        return asset('images/placeholders/partner-logo.webp');
    }
}
