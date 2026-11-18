<?php

namespace Tests\Feature\Policies;

use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_island_admin_sees_owners_on_own_islands_read_only(): void
    {
        $island = Island::factory()->create();
        $owner = Listing::factory()->onIsland($island)->create()->owner;
        $admin = $this->islandAdmin($island);

        $this->assertTrue($admin->can('view', $owner));
        $this->assertFalse($admin->can('update', $owner));
        $this->assertFalse($admin->can('deactivate', $owner));
        $this->assertFalse($admin->can('changeRole', $owner));
        $this->assertTrue($admin->can('recommendSuspension', $owner));
    }

    public function test_island_admin_cannot_see_owners_elsewhere_affiliates_or_staff(): void
    {
        $island = Island::factory()->create();
        $other = Island::factory()->create();
        $admin = $this->islandAdmin($island);

        $elsewhere = Listing::factory()->onIsland($other)->create()->owner;
        $affiliate = User::factory()->affiliate()->create();
        Listing::factory()->onIsland($island)->create(['owner_id' => $affiliate->id]);
        $staff = $this->superAdmin();

        $this->assertFalse($admin->can('view', $elsewhere));
        $this->assertFalse($admin->can('recommendSuspension', $elsewhere));
        $this->assertFalse($admin->can('view', $affiliate));
        $this->assertFalse($admin->can('viewAffiliateData', $affiliate));
        $this->assertFalse($admin->can('view', $staff));
    }

    public function test_super_admin_manages_accounts(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->owner()->create();
        $affiliate = User::factory()->affiliate()->create();

        $this->assertTrue($super->can('view', $owner));
        $this->assertTrue($super->can('update', $owner));
        $this->assertTrue($super->can('deactivate', $owner));
        $this->assertTrue($super->can('changeRole', $owner));
        $this->assertTrue($super->can('viewAffiliateData', $affiliate));
        $this->assertFalse($super->can('recommendSuspension', $owner), 'Super admins decide directly.');
    }

    public function test_nobody_changes_their_own_role(): void
    {
        $super = $this->superAdmin();
        $this->superAdmin();

        $this->assertFalse($super->can('changeRole', $super));
        $this->assertFalse($super->can('deactivate', $super));
    }

    /**
     * The last active super admin can never be demoted or deactivated: only another
     * super admin can do it (never yourself), so at least one always remains.
     */
    public function test_last_super_admin_cannot_be_demoted_or_deactivated(): void
    {
        $only = $this->superAdmin();
        $islandAdmin = $this->islandAdmin(\App\Models\Island::factory()->create());

        $this->assertFalse($only->can('changeRole', $only));
        $this->assertFalse($only->can('deactivate', $only));
        $this->assertFalse($islandAdmin->can('changeRole', $only));
        $this->assertFalse($islandAdmin->can('deactivate', $only));

        $second = $this->superAdmin();
        $this->assertTrue($second->can('changeRole', $only), 'With two super admins, one can demote the other.');
        $this->assertTrue($second->can('deactivate', $only));
    }

    public function test_owners_cannot_manage_other_accounts(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->owner()->create();

        foreach (['view', 'update', 'deactivate', 'changeRole'] as $ability) {
            $this->assertFalse($owner->can($ability, $other), $ability);
        }
    }

    public function test_affiliate_notes_are_hidden_from_serialisation(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $affiliate->forceFill(['affiliate_data' => 'Programme X, partner id 42'])->save();

        $this->assertArrayNotHasKey('affiliate_data', $affiliate->fresh()->toArray());
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $owner = User::factory()->owner()->create();
        $owner->update(['role' => 'super_admin', 'name' => 'New name']);

        $this->assertFalse($owner->fresh()->isSuperAdmin());
        $this->assertSame('New name', $owner->fresh()->name);
    }
}
