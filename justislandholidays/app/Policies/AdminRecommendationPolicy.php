<?php

namespace App\Policies;

use App\Models\AdminRecommendation;
use App\Models\User;

class AdminRecommendationPolicy
{
    /** Only a super admin accepts or declines a recommendation. */
    public function decide(User $user, AdminRecommendation $recommendation): bool
    {
        return $user->isSuperAdmin() && $recommendation->status === 'open';
    }

    public function view(User $user, AdminRecommendation $recommendation): bool
    {
        return $user->isAdmin() && $user->managesIsland($recommendation->island_id);
    }
}
