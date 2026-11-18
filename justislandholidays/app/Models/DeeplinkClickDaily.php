<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Composite key (listing_id, day): write with upsert(), not save(). */
class DeeplinkClickDaily extends Model
{
    protected $table = 'deeplink_clicks_daily';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }
}
