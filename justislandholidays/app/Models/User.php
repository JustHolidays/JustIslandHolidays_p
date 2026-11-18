<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Role, agency and affiliate notes are deliberately not fillable:
     * only a super admin changes them, through dedicated actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'preferred_lang',
        'marketing_consent',
        'consented_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'affiliate_data',
    ];

    /** Island ids this admin manages, loaded once per request. */
    private ?array $managedIslandIds = null;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'consented_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'owner_id');
    }

    /** Islands an island admin is assigned to (empty for everyone else). */
    public function islands(): BelongsToMany
    {
        return $this->belongsToMany(Island::class, 'island_admins')
            ->withPivot('assigned_by', 'created_at');
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('deactivated_at');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isIslandAdmin(): bool
    {
        return $this->role === Role::IslandAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function isAffiliate(): bool
    {
        return $this->role === Role::Affiliate;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return list<int> island ids for an island admin; empty for everyone else.
     * Super admins see every island, so callers must check isSuperAdmin() first.
     */
    public function managedIslandIds(): array
    {
        if (! $this->isIslandAdmin()) {
            return [];
        }

        return $this->managedIslandIds ??= $this->islands()
            ->pluck('islands.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function managesIsland(?int $islandId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $islandId !== null
            && $this->isIslandAdmin()
            && in_array($islandId, $this->managedIslandIds(), true);
    }

    /** Clears the per-request cache after island assignments change. */
    public function forgetManagedIslands(): void
    {
        $this->managedIslandIds = null;
    }
}
