<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Agent = 'agent';
    case Affiliate = 'affiliate';
    case IslandAdmin = 'island_admin';
    case SuperAdmin = 'super_admin';
    /** Someone looking for a holiday home: saves favourites. No plan, listings or admin (20 Nov 2026). */
    case Visitor = 'visitor';

    public function isAdmin(): bool
    {
        return $this === self::IslandAdmin || $this === self::SuperAdmin;
    }

    /** Roles that hold listings. */
    public function isLister(): bool
    {
        return in_array($this, [self::Owner, self::Agent, self::Affiliate], true);
    }

    /** Owners and agents pay to list; affiliates are outside billing. */
    public function canSubscribe(): bool
    {
        return $this === self::Owner || $this === self::Agent;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Agent => 'Agent',
            self::Affiliate => 'Affiliate',
            self::IslandAdmin => 'Island admin',
            self::SuperAdmin => 'Super admin',
            self::Visitor => 'Visitor',
        };
    }
}
