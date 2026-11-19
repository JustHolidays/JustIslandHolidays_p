<?php

namespace App\Jobs;

use App\Models\Listing;
use GdImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Makes the public copies of a listing photo: WebP at 400, 800 and 1600 pixels wide
 * (never enlarged), with EXIF rotation applied and metadata stripped.
 * The original stays in private storage.
 */
class ProcessListingImage implements ShouldQueue
{
    use Queueable;

    public const WIDTHS = [400, 800, 1600];

    /** $photoId set: an extra photo (listing_photos); otherwise the listing's main photo. */
    public function __construct(public int $listingId, public string $originalPath, public ?int $photoId = null)
    {
    }

    public function handle(): void
    {
        $listing = Listing::withTrashed()->find($this->listingId);
        if (! $listing || ! Storage::disk('local')->exists($this->originalPath)) {
            return;
        }

        $source = $this->load(Storage::disk('local')->path($this->originalPath));
        $base = "listings/{$listing->id}/".Str::uuid();
        $public = Storage::disk('public');

        foreach (self::WIDTHS as $width) {
            $public->put("{$base}-{$width}.webp", $this->webp($source, $width), 'public');
        }
        imagedestroy($source);

        if ($this->photoId !== null) {
            $photo = \App\Models\ListingPhoto::where('listing_id', $listing->id)->find($this->photoId);
            if (! $photo) {   // deleted while it was being processed
                foreach (self::WIDTHS as $width) {
                    $public->delete("{$base}-{$width}.webp");
                }

                return;
            }
            $old = $photo->image_path;
            $photo->forceFill(['image_path' => "{$base}-1600.webp"])->save();
        } else {
            $old = $listing->image_path;
            $listing->forceFill(['image_path' => "{$base}-1600.webp"])->save();
        }

        if ($old && preg_match('/^(listings\/\d+\/[0-9a-f-]+)-\d+\.webp$/', $old, $m)) {
            foreach (self::WIDTHS as $width) {
                $public->delete("{$m[1]}-{$width}.webp");
            }
        }
    }

    private function load(string $path): GdImage
    {
        $info = getimagesize($path);
        $image = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unsupported or damaged image.');
        }

        // Phone photos are often stored sideways with an EXIF rotation flag.
        if (($info[2] ?? null) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 1;
            $angle = match ((int) $orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0);
            }
        }

        return $image;
    }

    private function webp(GdImage $source, int $width): string
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $targetW = min($width, $w);
        $targetH = (int) round($h * $targetW / $w);

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $w, $h);

        ob_start();
        imagewebp($canvas, null, 80);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }
}
