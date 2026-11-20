<?php

namespace App\Support;

use GdImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Header images for island and resort pages: WebP copies at a few widths on the public
 * disk, so phones load a small one. The model keeps the path of the largest copy
 * (header_image_path) and its alt text (header_image_alt).
 */
final class HeaderImage
{
    public const WIDTHS = [800, 1600, 2400];

    public const MIN_WIDTH = 1600;

    /** Shown this tall on tablets and desktops (preferred, 19 Nov 2026); uploads must be at least this tall. */
    public const HEIGHT = 400;

    /** Placeholder shown until an admin adds a real image (public/images/placeholders). */
    public static function placeholderUrl(): string
    {
        return asset('images/placeholders/header-1600.webp');
    }

    public static function placeholderSrcset(): string
    {
        return collect(self::WIDTHS)->map(fn ($w) => asset("images/placeholders/header-{$w}.webp")." {$w}w")->implode(', ');
    }

    /** Saves the WebP copies and returns the path of the largest. */
    public static function store(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['header_image' => 'The image could not be read. Try a JPG or WebP file.']);
        }

        $base = 'places/'.Str::uuid();
        $largest = null;
        foreach (self::WIDTHS as $w) {
            if ($w > imagesx($source) && $largest !== null) {
                break;   // never upscale
            }
            $width = min($w, imagesx($source));
            $height = (int) round(imagesy($source) * $width / imagesx($source));

            $copy = imagecreatetruecolor($width, $height);
            imagecopyresampled($copy, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
            ob_start();
            imagewebp($copy, null, 80);
            Storage::disk('public')->put("{$base}-{$w}.webp", (string) ob_get_clean(), 'public');
            $largest = "{$base}-{$w}.webp";
        }

        return $largest;
    }

    /** Deletes every copy of a stored header image. */
    public static function delete(?string $path): void
    {
        if (! $path || ! preg_match('#^(places/[0-9a-f-]+)-\d+\.webp$#', $path, $m)) {
            return;
        }
        Storage::disk('public')->delete(array_map(fn ($w) => "{$m[1]}-{$w}.webp", self::WIDTHS));
    }

    public static function url(Model $place): ?string
    {
        return $place->header_image_path ? Storage::disk('public')->url($place->header_image_path) : null;
    }

    /** srcset for the copies that exist (up to the largest one stored). */
    public static function srcset(Model $place): string
    {
        return self::srcsetForPath((string) $place->header_image_path);
    }

    /** srcset for any image stored by store() (header images, the footer image). */
    public static function srcsetForPath(string $path): string
    {
        if (! preg_match('#^(places/[0-9a-f-]+)-(\d+)\.webp$#', $path, $m)) {
            return '';
        }

        return collect(self::WIDTHS)
            ->filter(fn ($w) => $w <= (int) $m[2])
            ->map(fn ($w) => Storage::disk('public')->url("{$m[1]}-{$w}.webp")." {$w}w")
            ->implode(', ');
    }
}
