<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Composite key (ad_id, stat_date): write with upsert(), not save(). */
class AdStatDaily extends Model
{
    protected $table = 'ad_stats_daily';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['stat_date' => 'date'];
    }
}
