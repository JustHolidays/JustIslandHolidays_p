<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Home page hero images; at most five active, enforced in the app. */
class HeroImage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['image_path', 'alt_text', 'sort_order', 'is_active', 'uploaded_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
