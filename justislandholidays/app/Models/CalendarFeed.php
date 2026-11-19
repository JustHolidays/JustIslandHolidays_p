<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An iCal address a property's availability is imported from. The address is private. */
class CalendarFeed extends Model
{
    protected $fillable = ['name', 'url'];

    protected $hidden = ['url'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime'];
    }

    public function listing(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CalendarBooking::class, 'feed_id');
    }
}
