<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Plan names, limits and prices live in the database, never in code. */
class Plan extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code', 'name', 'audience', 'max_listings', 'is_featured', 'search_boost', 'show_direct_contact',
        'currency', 'price_month_cents', 'price_year_cents', 'paypal_plan_id_month', 'paypal_plan_id_year', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'show_direct_contact' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
