<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/** Island admins edit guides tied to their islands; site-wide pages are super admin only. */
class PagePolicy
{
    public function view(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    public function create(User $user, ?int $islandId = null): bool
    {
        return $user->isSuperAdmin()
            || ($islandId !== null && $user->isIslandAdmin() && $user->managesIsland($islandId));
    }

    public function update(User $user, Page $page): bool
    {
        return $user->isSuperAdmin()
            || ($page->island_id !== null && $user->isIslandAdmin() && $user->managesIsland($page->island_id));
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }
}
