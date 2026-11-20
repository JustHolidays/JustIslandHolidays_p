<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One rate period: start_date to end_date (both included), prices in the listing's currency. */
class ListingRate extends Model
{
    /** Changes are in the audit log; no timestamps. */
    public $timestamps = false;

    /** Stored as plain dates (2027-06-15), so date comparisons work the same on MySQL and SQLite. */
    protected $dateFormat = 'Y-m-d';

    protected $fillable = ['start_date', 'end_date', 'nightly_cents', 'weekly_cents', 'fortnight_cents'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'nightly_cents' => 'integer',
            'weekly_cents' => 'integer',
            'fortnight_cents' => 'integer',
        ];
    }

    public function listing(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
