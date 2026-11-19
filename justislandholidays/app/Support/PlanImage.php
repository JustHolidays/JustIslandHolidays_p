<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Header images for plan cards: cropped from the centre to 8 by 3 and saved as one WebP,
 * 800 × 300, which is sharp at the card's size on high-density screens.
 */
final class PlanImage
{
    public static function store(UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src instanceof GdImage) {
            throw ValidationException::withMessages(['header_image' => 'The image could not be read. Try a JPG, PNG or WebP file.']);
        }

        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $cropW = min($sw, (int) floor($sh * 8 / 3));
        $cropH = (int) floor($cropW * 3 / 8);
        $out = imagecreatetruecolor(800, 300);
        imagecopyresampled($out, $src, 0, 0, (int) (($sw - $cropW) / 2), (int) (($sh - $cropH) / 2), 800, 300, $cropW, $cropH);

        ob_start();
        imagewebp($out, null, 82);
        $path = 'plans/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, (string) ob_get_clean(), 'public');

        return $path;
    }

    public static function placeholderUrl(): string
    {
        return asset('images/placeholders/plan-card.webp');
    }
}
