<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertiser extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['name', 'contact_email', 'notes'];

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
