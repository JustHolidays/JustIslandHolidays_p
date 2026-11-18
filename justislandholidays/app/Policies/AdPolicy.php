<?php

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;

/** Island admins manage ads targeted at their islands; all-island ads are super admin only. */
class AdPolicy
{
    public function create(User $user, ?int $islandId = null): bool
    {
        return $user->isSuperAdmin()
            || ($islandId !== null && $user->isIslandAdmin() && $user->managesIsland($islandId));
    }

    public function update(User $user, Ad $ad): bool
    {
        return $user->isSuperAdmin()
            || ($ad->island_id !== null && $user->isIslandAdmin() && $user->managesIsland($ad->island_id));
    }

    public function view(User $user, Ad $ad): bool
    {
        return $this->update($user, $ad);
    }
}
