<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A booked stay: nights from check_in up to (not including) check_out. feed_id null = marked by the owner. */
class CalendarBooking extends Model
{
    /** created_at is filled by the database; no updated_at. */
    public $timestamps = false;

    /** Stored as plain dates (2027-06-15), so date comparisons work the same on MySQL and SQLite. */
    protected $dateFormat = 'Y-m-d';

    protected $fillable = ['check_in', 'check_out'];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date'];
    }

    /** The imported calendar a stay came from; null when the owner marked it. */
    public function feed(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CalendarFeed::class, 'feed_id');
    }
}
