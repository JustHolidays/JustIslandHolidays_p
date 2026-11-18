<?php

namespace App\Models;

use App\Models\Concerns\HasJsonTranslations;
use Illuminate\Database\Eloquent\Model;

/** Also holds views and accessibility features (categories 'view' and 'accessibility'). */
class Amenity extends Model
{
    use HasJsonTranslations;

    public $timestamps = false;

    protected $fillable = ['code', 'category', 'name', 'icon', 'is_filterable', 'sort_order'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_filterable' => 'boolean'];
    }
}
