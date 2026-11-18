<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus as S;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Models\AuditLog;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class ListingWorkflowTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private ListingWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->workflow = app(ListingWorkflow::class);
    }

    public function test_lister_submits_a_complete_draft(): void
    {
        $listing = $this->completeListing();

        $this->workflow->submit($listing, $listing->owner);

        $this->assertSame(S::PendingReview, $listing->fresh()->status);
        $log = AuditLog::where('action', 'listing.submit')->sole();
        $this->assertSame(['status' => 'draft'], $log->detail['before']);
        $this->assertSame($listing->owner_id, $log->actor_id);
    }

    public function test_incomplete_draft_cannot_be_submitted(): void
    {
        $listing = $this->completeListing(attributes: ['image_path' => null]);

        try {
            $this->workflow->submit($listing, $listing->owner);
            $this->fail('Expected an exception');
        } catch (ListingWorkflowException $e) {
            $this->assertContains('Add a photo.', $e->problems);
        }
        $this->assertSame(S::Draft, $listing->fresh()->status);
    }

    public function test_owner_listing_needs_a_licence_number_but_affiliate_listing_needs_a_link(): void
    {
        $owned = $this->completeListing();
        $owned->licence->delete();
        $this->expectProblem(fn () => $this->workflow->submit($owned->fresh(), $owned->owner), 'Add your tourist licence number.');

        $affiliate = User::factory()->affiliate()->create();
        $partner = $this->completeListing($affiliate);
        $this->expectProblem(fn () => $this->workflow->submit($partner, $affiliate), 'Add the booking link for the Check availability button.');

        $partner->forceFill(['deeplink_url' => 'https://booking.example.com/1'])->save();
        $this->workflow->submit($partner->fresh(), $affiliate);
        $this->assertSame(S::PendingReview, $partner->fresh()->status);
    }

    public function test_someone_else_cannot_submit(): void
    {
        $listing = $this->completeListing();

        $this->expectException(ListingWorkflowException::class);
        $this->workflow->submit($listing, User::factory()->owner()->create());
    }

    public function test_approval_needs_a_verified_licence_and_a_checked_link(): void
    {
        $island = Island::factory()->create();
        $admin = $this->islandAdmin($island);
        $listing = $this->setStatus($this->completeListing(island: $island), S::PendingReview);

        $this->expectProblem(fn () => $this->workflow->approve($listing, $admin), 'The licence number has not been verified yet.');

        $listing->licence->forceFill(['status' => 'verified'])->save();
        $listing->forceFill(['deeplink_url' => 'https://booking.example.com/1', 'deeplink_status' => 'unchecked'])->save();
        $this->expectProblem(fn () => $this->workflow->approve($listing->fresh(), $admin), 'The Check availability link has not been checked yet.');

        $listing->forceFill(['deeplink_status' => 'ok'])->save();
        $this->workflow->approve($listing->fresh(), $admin);

        $fresh = $listing->fresh();
        $this->assertSame(S::Live, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_affiliate_listing_is_approved_without_a_licence(): void
    {
        $island = Island::factory()->create();
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->setStatus($this->completeListing($affiliate, $island, ['deeplink_url' => 'https://b.example/1', 'deeplink_status' => 'ok']), S::PendingReview);

        $this->workflow->approve($listing, $this->islandAdmin($island));

        $this->assertSame(S::Live, $listing->fresh()->status);
    }

    public function test_island_admin_of_another_island_cannot_approve(): void
    {
        $listing = $this->setStatus($this->completeListing(licenceVerified: true), S::PendingReview);

        $this->expectException(ListingWorkflowException::class);
        $this->workflow->approve($listing, $this->islandAdmin(Island::factory()->create()));
    }

    public function test_only_super_admin_rejects_and_a_reason_is_required(): void
    {
        $island = Island::factory()->create();
        $listing = $this->setStatus($this->completeListing(island: $island), S::PendingReview);

        $this->expectProblem(fn () => $this->workflow->reject($listing, $this->islandAdmin($island), 'Bad photos'), null, 'You do not have permission to do that.');
        $this->expectProblem(fn () => $this->workflow->reject($listing, $this->superAdmin(), '  '), null, 'Please give a reason. It is sent to the lister.');

        $this->workflow->reject($listing, $this->superAdmin(), 'The photo is of another property.');

        $fresh = $listing->fresh();
        $this->assertSame(S::Rejected, $fresh->status);
        $this->assertSame('The photo is of another property.', $fresh->rejection_reason);
        $this->assertSame('The photo is of another property.', AuditLog::where('action', 'listing.reject')->sole()->detail['reason']);
    }

    public function test_rejected_listing_can_be_reopened_by_lister(): void
    {
        $listing = $this->setStatus($this->completeListing(), S::Rejected);

        $this->workflow->reopen($listing, $listing->owner);

        $this->assertSame(S::Draft, $listing->fresh()->status);
    }

    public function test_pause_resume_and_archive(): void
    {
        $listing = $this->setStatus($this->completeListing(), S::Live);
        $owner = $listing->owner;

        $this->workflow->pause($listing, $owner);
        $this->assertSame(S::Paused, $listing->fresh()->status);

        $this->workflow->resume($listing->fresh(), $owner);
        $this->assertSame(S::Live, $listing->fresh()->status);

        $this->workflow->archive($listing->fresh(), $owner);
        $this->assertSame(S::Archived, $listing->fresh()->status);

        $this->expectException(ListingWorkflowException::class);
        $this->workflow->resume($listing->fresh(), $owner);
    }

    public function test_admin_pause_needs_a_reason(): void
    {
        $island = Island::factory()->create();
        $listing = $this->setStatus($this->completeListing(island: $island), S::Live);

        $this->expectProblem(fn () => $this->workflow->pause($listing, $this->islandAdmin($island)), null, 'Please give a reason. It is sent to the lister.');

        $this->workflow->pause($listing, $this->islandAdmin($island), 'Complaint under review');
        $this->assertSame(S::Paused, $listing->fresh()->status);
    }

    public function test_free_plan_allows_one_listing(): void
    {
        $owner = User::factory()->owner()->create();
        $this->setStatus($this->completeListing($owner), S::Live);
        $second = $this->completeListing($owner);

        $this->expectProblem(fn () => $this->workflow->submit($second, $owner), null, 'Your Free plan allows 1 listing. Pause or archive another listing, or choose a bigger plan.');
    }

    public function test_live_listing_goes_back_to_review_when_sent_back(): void
    {
        $listing = $this->setStatus($this->completeListing(), S::Live);

        $this->workflow->sendBackForReview($listing, $listing->owner, 'The Check availability link changed.');

        $this->assertSame(S::PendingReview, $listing->fresh()->status);
        $this->assertSame('The Check availability link changed.', AuditLog::where('action', 'listing.back_to_review')->sole()->detail['reason']);
    }

    private function expectProblem(callable $fn, ?string $problem, ?string $message = null): void
    {
        try {
            $fn();
            $this->fail('Expected a ListingWorkflowException');
        } catch (ListingWorkflowException $e) {
            if ($problem !== null) {
                $this->assertContains($problem, $e->problems);
            }
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }
}
