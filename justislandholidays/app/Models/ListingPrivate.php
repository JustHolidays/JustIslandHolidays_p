<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exact location, address and contact details. Never exposed to the public. */
class ListingPrivate extends Model
{
    protected $table = 'listing_private';

    protected $primaryKey = 'listing_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['lat', 'lng', 'address_line', 'postcode', 'contact_email', 'contact_phone', 'website_url', 'show_phone'];

    protected function casts(): array
    {
        return ['show_phone' => 'boolean'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
