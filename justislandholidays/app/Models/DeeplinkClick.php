<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Check availability click. ip_hash is a salted daily hash; the raw IP is never stored. */
class DeeplinkClick extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'is_bot' => 'boolean',
            'is_unique' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }
}
