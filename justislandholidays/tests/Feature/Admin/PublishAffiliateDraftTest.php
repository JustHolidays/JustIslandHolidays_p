<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Approve and publish for affiliate drafts on the admin property page (21 Nov 2026). */
class PublishAffiliateDraftTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private Island $island;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->island = Island::factory()->create(['is_active' => true]);
    }

    private function affiliateDraft(string $linkStatus = 'ok', ?Island $island = null): Listing
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->completeListing($affiliate, $island ?? $this->island, ['min_price_week_cents' => null]);
        $listing->forceFill(['deeplink_url' => 'https://booking.example.com/home/1', 'deeplink_status' => $linkStatus])->save();

        return $listing->fresh();
    }

    public function test_a_ready_affiliate_draft_is_published_in_one_step(): void
    {
        $admin = $this->superAdmin();
        $draft = $this->affiliateDraft();

        $this->actingAs($admin)->get(route('admin.listings.show', $draft))->assertOk()
            ->assertSee('data-publish-draft', false)->assertSee('Approve and publish');

        $this->actingAs($admin)->post(route('admin.listings.publish-draft', $draft))
            ->assertRedirect(route('admin.listings.show', $draft))->assertSessionHas('status', 'Published. The listing is live.');

        $draft->refresh();
        $this->assertSame(ListingStatus::Live, $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertTrue(AuditLog::where('action', 'listing.submit')->where('entity_id', (string) $draft->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'listing.approve')->where('entity_id', (string) $draft->id)->exists());
    }

    public function test_a_draft_that_is_not_ready_stays_a_draft(): void
    {
        $admin = $this->superAdmin();
        $draft = $this->affiliateDraft('unchecked');

        $this->actingAs($admin)->get(route('admin.listings.show', $draft))->assertOk()
            ->assertSee('The Check availability link has not been checked yet.')
            ->assertSee('Not ready yet: see the Checklist.');

        $this->actingAs($admin)->from(route('admin.listings.show', $draft))->post(route('admin.listings.publish-draft', $draft))
            ->assertSessionHasErrors();

        $this->assertSame(ListingStatus::Draft, $draft->fresh()->status, 'Not left waiting for review either.');
        $this->assertFalse(AuditLog::where('action', 'listing.submit')->exists());
    }

    public function test_island_admins_publish_on_their_own_islands_only(): void
    {
        $other = Island::factory()->create(['is_active' => true]);
        $islandAdmin = $this->islandAdmin($this->island);
        $mine = $this->affiliateDraft();
        $theirs = $this->affiliateDraft('ok', $other);

        $this->actingAs($islandAdmin)->post(route('admin.listings.publish-draft', $theirs))->assertForbidden();
        $this->assertSame(ListingStatus::Draft, $theirs->fresh()->status);

        $this->actingAs($islandAdmin)->post(route('admin.listings.publish-draft', $mine))->assertRedirect();
        $this->assertSame(ListingStatus::Live, $mine->fresh()->status);
    }

    public function test_owner_drafts_and_non_drafts_are_refused(): void
    {
        $admin = $this->superAdmin();
        $ownerDraft = $this->completeListing(null, $this->island, [], true);
        $pending = $this->setStatus($this->affiliateDraft(), ListingStatus::PendingReview);

        $this->actingAs($admin)->get(route('admin.listings.show', $ownerDraft))->assertOk()
            ->assertDontSee('data-publish-draft', false)->assertSee('sends it for review from their account');
        $this->actingAs($admin)->from(route('admin.listings.show', $ownerDraft))->post(route('admin.listings.publish-draft', $ownerDraft))
            ->assertSessionHasErrors();
        $this->assertSame(ListingStatus::Draft, $ownerDraft->fresh()->status);

        $this->actingAs($admin)->from(route('admin.listings.show', $pending))->post(route('admin.listings.publish-draft', $pending))
            ->assertSessionHasErrors();
        $this->assertSame(ListingStatus::PendingReview, $pending->fresh()->status);
    }

    public function test_affiliate_pages_say_supplied_by_a_partner_without_the_email(): void
    {
        $admin = $this->superAdmin();
        $draft = $this->affiliateDraft();
        $owned = $this->completeListing(null, $this->island);

        $this->actingAs($admin)->get(route('admin.listings.show', $draft))->assertOk()
            ->assertSee('Supplied by')->assertSee('(Partner)')
            ->assertDontSee('Listed by')->assertDontSee($draft->owner->email);
        $this->actingAs($admin)->get(route('admin.listings.show', $owned))->assertOk()
            ->assertSee('Listed by')->assertSee($owned->owner->email)->assertDontSee('Supplied by');
    }

    public function test_listers_cannot_use_it(): void
    {
        $draft = $this->affiliateDraft();

        $this->actingAs($draft->owner)->post(route('admin.listings.publish-draft', $draft))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->post(route('admin.listings.publish-draft', $draft))->assertForbidden();
        $this->assertSame(ListingStatus::Draft, $draft->fresh()->status);
    }
}
