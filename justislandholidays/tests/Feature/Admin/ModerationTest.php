<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Models\AdminRecommendation;
use App\Models\AuditLog;
use App\Models\DeeplinkClick;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingNeedsReview;
use App\Notifications\ListingStatusChanged;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private Island $island;

    private Island $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Notification::fake();
        $this->island = Island::factory()->create();
        $this->other = Island::factory()->create();
        $this->admin = $this->islandAdmin($this->island);
    }

    private function pending(?Island $island = null, ?int $hoursAgo = 1, bool $verified = true): Listing
    {
        $listing = $this->setStatus($this->completeListing(island: $island ?? $this->island, licenceVerified: $verified), S::PendingReview);
        $log = Audit::record($listing->owner, 'listing.submit', $listing);
        $log->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

        return $listing;
    }

    public function test_queue_shows_own_island_pending_listings_oldest_first_and_flags_late_ones(): void
    {
        $new = $this->pending(hoursAgo: 2);
        $old = $this->pending(hoursAgo: 60);
        $elsewhere = $this->pending($this->other);

        $response = $this->actingAs($this->admin)->get('/admin/listings')->assertOk();

        $response->assertSeeInOrder([route('admin.listings.show', $old), route('admin.listings.show', $new)], false)
            ->assertDontSee(route('admin.listings.show', $elsewhere), false)
            ->assertSee('Over 48 hours');
    }

    public function test_dashboard_counts_are_scoped_and_link_to_lists(): void
    {
        $this->pending(hoursAgo: 60);
        $this->pending($this->other);

        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSeeInOrder(['Listings waiting for review', '1', '1 waiting more than 48 hours']);
    }

    public function test_review_page_and_approve(): void
    {
        $listing = $this->pending();

        $this->actingAs($this->admin)->get("/admin/listings/{$listing->id}")->assertOk()->assertSee('Everything is in place');
        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/approve")->assertRedirect();

        $this->assertSame(S::Live, $listing->fresh()->status);
        Notification::assertSentTo($listing->owner, ListingStatusChanged::class, fn ($n) => $n->action === 'listing.approve');
    }

    public function test_approve_is_refused_without_a_verified_licence(): void
    {
        $listing = $this->pending(verified: false);

        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/approve")->assertSessionHasErrors();
        $this->assertSame(S::PendingReview, $listing->fresh()->status);
    }

    public function test_island_admin_cannot_reject_but_can_recommend(): void
    {
        $listing = $this->pending();

        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/reject", ['reason' => 'Fake photo'])->assertSessionHasErrors();
        $this->assertSame(S::PendingReview, $listing->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/recommend-rejection", ['reason' => 'Photo is from another site'])->assertSessionHasNoErrors();
        $rec = AdminRecommendation::sole();
        $this->assertSame('reject_listing', $rec->action);
        $this->assertSame($this->island->id, $rec->island_id);

        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/recommend-rejection", ['reason' => 'again'])->assertSessionHasErrors();
    }

    public function test_super_admin_accepts_a_recommendation_and_the_lister_gets_the_reason(): void
    {
        $listing = $this->pending();
        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/recommend-rejection", ['reason' => 'Photo is from another site.']);
        $rec = AdminRecommendation::sole();
        $super = $this->superAdmin();

        $this->actingAs($super)->get('/admin/listings?tab=recommended')->assertOk()->assertSee($listing->title('en'));
        $this->actingAs($super)->post("/admin/recommendations/{$rec->id}/decide", ['decision' => 'accept', 'decision_note' => 'Please upload your own photo.']);

        $this->assertSame(S::Rejected, $listing->fresh()->status);
        $this->assertSame('Photo is from another site. Please upload your own photo.', $listing->fresh()->rejection_reason);
        $this->assertSame('accepted', $rec->fresh()->status);
        Notification::assertSentTo($listing->owner, ListingStatusChanged::class, fn ($n) => $n->action === 'listing.reject' && str_contains($n->reason, 'own photo'));
    }

    public function test_declined_recommendation_changes_nothing_and_island_admins_cannot_decide(): void
    {
        $listing = $this->pending();
        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/recommend-rejection", ['reason' => 'Doubtful']);
        $rec = AdminRecommendation::sole();

        $this->actingAs($this->admin)->post("/admin/recommendations/{$rec->id}/decide", ['decision' => 'accept'])->assertForbidden();
        $this->actingAs($this->superAdmin())->post("/admin/recommendations/{$rec->id}/decide", ['decision' => 'decline']);

        $this->assertSame(S::PendingReview, $listing->fresh()->status);
        $this->assertSame('declined', $rec->fresh()->status);
    }

    public function test_pause_and_archive_need_a_reason_which_is_emailed(): void
    {
        $listing = $this->setStatus($this->completeListing(island: $this->island), S::Live);

        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/pause", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post("/admin/listings/{$listing->id}/pause", ['reason' => 'Complaint received'])->assertSessionHasNoErrors();

        $this->assertSame(S::Paused, $listing->fresh()->status);
        Notification::assertSentTo($listing->owner, ListingStatusChanged::class, fn ($n) => $n->reason === 'Complaint received');
    }

    public function test_bulk_pause_only_touches_listings_on_the_admins_islands(): void
    {
        $mine = $this->setStatus($this->completeListing(island: $this->island), S::Live);
        $theirs = $this->setStatus($this->completeListing(island: $this->other), S::Live);

        $this->actingAs($this->admin)->post('/admin/listings/bulk', ['action' => 'pause', 'ids' => [$mine->id, $theirs->id], 'reason' => 'Season closed']);

        $this->assertSame(S::Paused, $mine->fresh()->status);
        $this->assertSame(S::Live, $theirs->fresh()->status);
    }

    public function test_admin_edit_is_audited_with_before_and_after(): void
    {
        $listing = $this->pending();

        $this->actingAs($this->admin)->put("/admin/listings/{$listing->id}", [
            'title' => 'Corrected title', 'summary' => 'Fixed text', 'area_id' => $listing->area_id, 'property_type' => 'villa',
            'bedrooms' => 4, 'bathrooms' => 2, 'sleeps' => 8, 'pets' => 'not_stated', 'price' => 1200,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Corrected title', $listing->fresh()->title('en'));
        $log = AuditLog::where('action', 'listing.admin_edit')->sole();
        $this->assertSame('Villa Test', $log->detail['before']['title']);
        $this->assertSame('Corrected title', $log->detail['after']['title']);
    }

    public function test_other_island_records_are_refused_for_every_action(): void
    {
        $theirs = $this->pending($this->other);

        foreach (['approve', 'recommend-rejection', 'pause', 'archive', 'check-link'] as $action) {
            $this->actingAs($this->admin)->post("/admin/listings/{$theirs->id}/{$action}", ['reason' => 'x x x'])->assertForbidden();
        }
        $this->actingAs($this->admin)->get("/admin/listings/{$theirs->id}/test-link")->assertForbidden();
    }

    public function test_test_link_records_no_click(): void
    {
        $listing = $this->setStatus($this->completeListing(island: $this->island, attributes: ['deeplink_url' => 'https://booking.example.com/x']), S::Live);

        $this->actingAs($this->admin)->get("/admin/listings/{$listing->id}/test-link")->assertRedirect('https://booking.example.com/x');
        $this->assertSame(0, DeeplinkClick::count());
    }

    public function test_reviewers_are_told_about_new_listings_or_super_admins_when_the_island_has_none(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->owner()->create();

        $here = $this->completeListing($owner, $this->island);
        app(\App\Listings\ListingWorkflow::class)->submit($here, $owner);
        Notification::assertSentTo($this->admin, ListingNeedsReview::class);
        Notification::assertNotSentTo($super, ListingNeedsReview::class);

        $owner2 = User::factory()->owner()->create();
        $there = $this->completeListing($owner2, $this->other);
        app(\App\Listings\ListingWorkflow::class)->submit($there, $owner2);
        Notification::assertSentTo($super, ListingNeedsReview::class);
    }
}
