<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Every admin list and detail query goes through here.
 * Super admins see every island; island admins only the islands in island_admins.
 * Records with no island (site-wide pages, all-island ads) are super admin only.
 */
final class AdminScope
{
    /** Limits a query on a table that has its own island column. */
    public static function apply(Builder $query, User $admin, string $column = 'island_id'): Builder
    {
        self::ensureAdmin($admin);

        if ($admin->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn($column), $admin->managedIslandIds());
    }

    /** Limits a query through a relation that has the island column, e.g. enquiries via 'listing'. */
    public static function applyVia(Builder $query, User $admin, string $relation, string $column = 'island_id'): Builder
    {
        self::ensureAdmin($admin);

        if ($admin->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $q) use ($admin, $column) {
            $q->whereIn($q->qualifyColumn($column), $admin->managedIslandIds());
        });
    }

    /** Refuses (403) when the admin does not cover the record's island. */
    public static function authorize(User $admin, ?int $islandId): void
    {
        self::ensureAdmin($admin);

        if (! $admin->managesIsland($islandId)) {
            throw new HttpException(403, 'This record is on an island you do not manage.');
        }
    }

    private static function ensureAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new HttpException(403);
        }
    }
}
