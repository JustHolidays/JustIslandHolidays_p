<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** island_id NULL means the ad shows on every island (super admins only). */
class Ad extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'advertiser_id', 'slot_id', 'island_id', 'image_path', 'alt', 'target_url',
        'status', 'weight', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class, 'slot_id');
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }
}
