<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Owner and agent listings only. Affiliate listings have no row. */
class ListingLicence extends Model
{
    protected $primaryKey = 'listing_id';

    public $incrementing = false;

    public $timestamps = false;

    /** Status, verifier and notes are set only through the licence check actions. */
    protected $fillable = ['registration_number', 'national_registry_no', 'document_path'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
