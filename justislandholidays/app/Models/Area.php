<?php

namespace App\Models;

use App\Models\Concerns\HasJsonTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A resort or town on an island. */
class Area extends Model
{
    use HasFactory, HasJsonTranslations;

    public $timestamps = false;

    protected $fillable = ['island_id', 'slug', 'kind', 'name', 'intro', 'centre_lat', 'centre_lng', 'sort_order'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'intro' => 'array',
            'centre_lat' => 'decimal:6',
            'centre_lng' => 'decimal:6',
        ];
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
