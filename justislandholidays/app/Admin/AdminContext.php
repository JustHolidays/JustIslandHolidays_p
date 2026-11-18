<?php

namespace App\Admin;

use App\Models\Island;
use App\Models\User;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Per-request admin state: who the admin is, which islands they cover, and which one
 * the island selector has picked. Every admin list goes through scope()/scopeVia(),
 * which apply AdminScope and then the selector.
 */
final class AdminContext
{
    public const SESSION_KEY = 'admin.island';

    private ?Collection $islands = null;

    public function __construct(private Request $request)
    {
    }

    public function admin(): User
    {
        return $this->request->user();
    }

    /** Islands this admin covers (super admins: all of them). */
    public function islands(): Collection
    {
        if ($this->islands) {
            return $this->islands;
        }

        $admin = $this->admin();

        return $this->islands = $admin->isSuperAdmin()
            ? Island::orderBy('sort_order')->get()
            : Island::whereIn('id', $admin->managedIslandIds())->orderBy('sort_order')->get();
    }

    /** The island chosen in the header selector, or null for all of the admin's islands. */
    public function selectedIslandId(): ?int
    {
        $id = $this->request->session()->get(self::SESSION_KEY);

        return $id !== null && $this->islands()->contains('id', (int) $id) ? (int) $id : null;
    }

    public function selectedIsland(): ?Island
    {
        $id = $this->selectedIslandId();

        return $id ? $this->islands()->firstWhere('id', $id) : null;
    }

    public function select(?int $islandId): void
    {
        if ($islandId === null || ! $this->islands()->contains('id', $islandId)) {
            $this->request->session()->forget(self::SESSION_KEY);

            return;
        }

        $this->request->session()->put(self::SESSION_KEY, $islandId);
    }

    public function scope(Builder $query, string $column = 'island_id'): Builder
    {
        AdminScope::apply($query, $this->admin(), $column);

        if ($id = $this->selectedIslandId()) {
            $query->where($query->qualifyColumn($column), $id);
        }

        return $query;
    }

    public function scopeVia(Builder $query, string $relation, string $column = 'island_id'): Builder
    {
        AdminScope::applyVia($query, $this->admin(), $relation, $column);

        if ($id = $this->selectedIslandId()) {
            $query->whereHas($relation, fn (Builder $q) => $q->where($q->qualifyColumn($column), $id));
        }

        return $query;
    }

    /** Refuses (403) a record on an island this admin does not cover. */
    public function authorizeIsland(?int $islandId): void
    {
        AdminScope::authorize($this->admin(), $islandId);
    }
}
