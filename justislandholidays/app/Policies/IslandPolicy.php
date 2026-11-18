<?php

namespace App\Policies;

use App\Models\Island;
use App\Models\User;

class IslandPolicy
{
    /** Island admins edit the introduction text for their own islands. */
    public function updateText(User $user, Island $island): bool
    {
        return $user->isAdmin() && $user->managesIsland($island->id);
    }

    /** Create, rename, set map centre and order, switch on or off: super admin only. */
    public function manage(User $user, Island $island): bool
    {
        return $user->isSuperAdmin();
    }
}
