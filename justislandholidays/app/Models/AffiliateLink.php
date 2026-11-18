<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The platform's own travel partner links (flights, car hire). Not related to the affiliate role.
 */
class AffiliateLink extends Model
{
    public $timestamps = false;

    protected $fillable = ['partner', 'category', 'island_id', 'url_template', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
