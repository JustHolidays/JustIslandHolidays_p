<?php

namespace Tests\Feature\Policies;

use App\Models\Agency;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    private Island $otherIsland;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->island = Island::factory()->create();
        $this->otherIsland = Island::factory()->create();
        $this->listing = Listing::factory()->onIsland($this->island)->create();
    }

    public function test_owner_can_view_update_submit_pause_and_archive_own_listing(): void
    {
        $owner = $this->listing->owner;

        foreach (['view', 'update', 'submit', 'pause', 'archive', 'submitLicence'] as $ability) {
            $this->assertTrue($owner->can($ability, $this->listing), $ability);
        }
    }

    public function test_owner_cannot_approve_reject_or_override_own_listing(): void
    {
        $owner = $this->listing->owner;

        foreach (['approve', 'reject', 'recommendRejection', 'overridePlan'] as $ability) {
            $this->assertFalse($owner->can($ability, $this->listing), $ability);
        }
    }

    public function test_another_owner_cannot_touch_the_listing(): void
    {
        $stranger = User::factory()->owner()->create();

        foreach (['view', 'update', 'submit', 'pause', 'archive'] as $ability) {
            $this->assertFalse($stranger->can($ability, $this->listing), $ability);
        }
    }

    public function test_agency_member_can_edit_agency_listings(): void
    {
        $agency = Agency::factory()->create();
        $listing = Listing::factory()->onIsland($this->island)->create();
        $listing->forceFill(['agency_id' => $agency->id])->save();

        $colleague = User::factory()->agent()->create();
        $colleague->forceFill(['agency_id' => $agency->id])->save();

        $this->assertTrue($colleague->can('update', $listing));
    }

    public function test_island_admin_can_approve_but_not_reject_on_own_island(): void
    {
        $admin = $this->islandAdmin($this->island);

        $this->assertTrue($admin->can('update', $this->listing));
        $this->assertTrue($admin->can('approve', $this->listing));
        $this->assertTrue($admin->can('pause', $this->listing));
        $this->assertTrue($admin->can('recommendRejection', $this->listing));
        $this->assertFalse($admin->can('reject', $this->listing));
        $this->assertFalse($admin->can('overridePlan', $this->listing));
    }

    public function test_island_admin_cannot_act_on_another_island(): void
    {
        $admin = $this->islandAdmin($this->otherIsland);

        foreach (['view', 'update', 'approve', 'pause', 'archive', 'recommendRejection'] as $ability) {
            $this->assertFalse($admin->can($ability, $this->listing), $ability);
        }
    }

    public function test_super_admin_can_approve_and_reject_anywhere(): void
    {
        $super = $this->superAdmin();

        foreach (['view', 'update', 'approve', 'reject', 'pause', 'archive', 'overridePlan'] as $ability) {
            $this->assertTrue($super->can($ability, $this->listing), $ability);
        }
        $this->assertFalse($super->can('recommendRejection', $this->listing), 'Super admins reject directly.');
    }

    public function test_affiliate_listing_has_no_licence_to_submit(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = Listing::factory()->onIsland($this->island)->create(['owner_id' => $affiliate->id]);

        $this->assertTrue($affiliate->can('update', $listing));
        $this->assertFalse($affiliate->can('submitLicence', $listing));
    }

    public function test_protected_listing_fields_are_not_mass_assignable(): void
    {
        $this->listing->fill([
            'status' => 'live',
            'plan_code' => 'featured',
            'rank_score' => 999,
            'owner_id' => 12345,
            'published_at' => now(),
            'deeplink_status' => 'ok',
            'bedrooms' => 4,
        ]);

        $this->assertSame('draft', $this->listing->status->value);
        $this->assertSame('free', $this->listing->fresh()->plan_code);
        $this->assertNotSame(12345, $this->listing->owner_id);
        $this->assertSame(4, $this->listing->bedrooms);
    }

    public function test_listing_gets_a_unique_public_ref(): void
    {
        $other = Listing::factory()->onIsland($this->island)->create();

        $this->assertMatchesRegularExpression('/^[a-z2-9]{8}$/', $this->listing->public_ref);
        $this->assertNotSame($this->listing->public_ref, $other->public_ref);
    }

    public function test_live_scope_returns_only_live_listings(): void
    {
        $live = Listing::factory()->onIsland($this->island)->live()->create();
        $deleted = Listing::factory()->onIsland($this->island)->live()->create();
        $deleted->delete();

        $this->assertSame([$live->id], Listing::live()->pluck('id')->all());
    }
}
