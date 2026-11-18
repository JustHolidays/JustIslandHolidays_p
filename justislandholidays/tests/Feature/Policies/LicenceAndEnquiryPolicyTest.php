<?php

namespace Tests\Feature\Policies;

use App\Models\Enquiry;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenceAndEnquiryPolicyTest extends TestCase
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

    private function licence(): ListingLicence
    {
        $licence = new ListingLicence(['registration_number' => 'ETV/1234']);
        $licence->listing_id = $this->listing->id;
        $licence->status = 'pending';
        $licence->save();

        return $licence->fresh();
    }

    public function test_island_admin_verifies_licences_on_own_island_only(): void
    {
        $licence = $this->licence();

        $this->assertTrue($this->islandAdmin($this->island)->can('verify', $licence));
        $this->assertTrue($this->islandAdmin($this->island)->can('reject', $licence));
        $this->assertFalse($this->islandAdmin($this->otherIsland)->can('verify', $licence));
        $this->assertTrue($this->superAdmin()->can('verify', $licence));
    }

    public function test_owner_cannot_verify_own_licence_but_can_see_the_document(): void
    {
        $licence = $this->licence();
        $owner = $this->listing->owner;

        $this->assertFalse($owner->can('verify', $licence));
        $this->assertTrue($owner->can('viewDocument', $licence));
        $this->assertFalse(User::factory()->owner()->create()->can('viewDocument', $licence));
    }

    public function test_enquiry_recipient_can_read_and_change_status(): void
    {
        $enquiry = Enquiry::factory()->for($this->listing)->create();
        $owner = $this->listing->owner;

        $this->assertTrue($owner->can('view', $enquiry));
        $this->assertTrue($owner->can('updateStatus', $enquiry));
        $this->assertTrue($owner->can('markSpam', $enquiry));
    }

    public function test_other_users_cannot_read_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->for($this->listing)->create();

        $this->assertFalse(User::factory()->owner()->create()->can('view', $enquiry));
        $this->assertFalse(User::factory()->affiliate()->create()->can('view', $enquiry));
    }

    public function test_admins_read_enquiries_for_their_islands_but_cannot_change_status(): void
    {
        $enquiry = Enquiry::factory()->for($this->listing)->create();
        $admin = $this->islandAdmin($this->island);

        $this->assertTrue($admin->can('view', $enquiry));
        $this->assertTrue($admin->can('markSpam', $enquiry));
        $this->assertFalse($admin->can('updateStatus', $enquiry));
        $this->assertFalse($this->islandAdmin($this->otherIsland)->can('view', $enquiry));
        $this->assertTrue($this->superAdmin()->can('view', $enquiry));
    }
}
