<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composite key (listing_id, lang). Eloquent has no composite keys, so update rows
 * with a query (updateOrInsert) rather than save() on a loaded model.
 */
class ListingTranslation extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['listing_id', 'lang', 'title', 'summary', 'is_machine_translated'];

    protected function casts(): array
    {
        return ['is_machine_translated' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
