<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    /** Island admins edit the introduction text for resorts on their own islands. */
    public function updateText(User $user, Area $area): bool
    {
        return $user->isAdmin() && $user->managesIsland($area->island_id);
    }

    /** Create, rename, change slug or map centre: super admin only. */
    public function manage(User $user, Area $area): bool
    {
        return $user->isSuperAdmin();
    }
}
