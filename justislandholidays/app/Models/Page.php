<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** island_id NULL means a site-wide page (about, terms, privacy): super admins only. */
class Page extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'island_id', 'kind', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
