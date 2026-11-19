<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An extra photo after a listing's main photo (listings.image_path), added 19 Nov 2026.
 * How many show depends on the plan (Plan::photoLimit, which counts the main photo).
 */
class ListingPhoto extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['alt', 'sort_order'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function url(int $width = 1600): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url(preg_replace('/-1600\.webp$/', "-{$width}.webp", $this->image_path));
    }

    /** Deletes this photo's public copies. */
    public function deleteFiles(): void
    {
        if ($this->image_path && preg_match('/^(listings\/\d+\/[0-9a-f-]+)-\d+\.webp$/', $this->image_path, $m)) {
            foreach (\App\Jobs\ProcessListingImage::WIDTHS as $w) {
                Storage::disk('public')->delete("{$m[1]}-{$w}.webp");
            }
        }
    }
}
