<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Admin actions on other people's accounts (Owners and agents, Affiliates, Staff). */
class UserPolicy
{
    /**
     * Super admins see everyone. Island admins see, read-only, owners and agents
     * with at least one listing on their islands. Never affiliates or staff.
     */
    public function view(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isIslandAdmin()
            && in_array($target->role, [Role::Owner, Role::Agent], true)
            && $target->listings()->whereIn('island_id', $actor->managedIslandIds())->exists();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin();
    }

    /** Nobody changes their own role, and the last super admin cannot be demoted. */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            && $actor->id !== $target->id
            && ! $this->isLastSuperAdmin($target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            && $actor->id !== $target->id
            && ! $this->isLastSuperAdmin($target);
    }

    /** Island admins cannot suspend; they recommend it for a super admin to decide. */
    public function recommendSuspension(User $actor, User $target): bool
    {
        return $actor->isIslandAdmin() && $this->view($actor, $target);
    }

    /** The private affiliate notes: super admins only. */
    public function viewAffiliateData(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && $target->isAffiliate();
    }

    private function isLastSuperAdmin(User $target): bool
    {
        return $target->isSuperAdmin()
            && ! $target->isDeactivated()
            && User::active()->where('role', Role::SuperAdmin)->count() <= 1;
    }
}
