<?php

namespace App\Models;

use App\Models\Concerns\HasJsonTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Island extends Model
{
    use HasFactory, HasJsonTranslations;

    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'intro', 'centre_lat', 'centre_lng', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'intro' => 'array',
            'is_active' => 'boolean',
            'centre_lat' => 'decimal:6',
            'centre_lng' => 'decimal:6',
        ];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'island_admins')->withPivot('assigned_by', 'created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
