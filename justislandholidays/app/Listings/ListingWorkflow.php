<?php

namespace App\Listings;

use App\Billing\AccountPlan;
use App\Enums\ListingStatus as S;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The only place a listing's status changes. Each change checks who may make it,
 * checks the listing is ready, and writes the audit log.
 *
 * Draft -> Pending review (lister) -> Live (island admin for that island, or super admin)
 * Pending review -> Rejected (super admin, with a reason) ; Rejected -> Draft (lister)
 * Live <-> Paused, Live/Paused -> Archived (lister or admin; admins give a reason)
 * Live -> Pending review automatically when the deeplink or licence number changes.
 */
final class ListingWorkflow
{
    public function __construct(private AccountPlan $plans)
    {
    }

    public function submit(Listing $listing, User $actor): void
    {
        $this->authorize($actor, 'submit', $listing);
        $this->expect($listing, [S::Draft], 'Only a draft can be sent for review.');

        $missing = ListingChecklist::missingForSubmit($listing);
        if ($missing !== []) {
            throw new ListingWorkflowException('Your listing is not ready to send yet.', $missing);
        }

        $this->ensureWithinPlanLimit($listing);

        $this->move($listing, S::PendingReview, $actor, 'listing.submit');
        $this->notifyReviewers($listing);
    }

    /** An admin sends a listing they created (URL import) for review on the affiliate's behalf. */
    public function submitOnBehalf(Listing $listing, User $admin): void
    {
        if (! $admin->isAdmin() || ! $admin->managesIsland($listing->island_id)) {
            throw new ListingWorkflowException('You do not have permission to do that.');
        }
        $this->expect($listing, [S::Draft], 'Only a draft can be sent for review.');

        $missing = ListingChecklist::missingForSubmit($listing);
        if ($missing !== []) {
            throw new ListingWorkflowException('The listing is not ready to send yet.', $missing);
        }

        $this->move($listing, S::PendingReview, $admin, 'listing.submit');
    }

    /**
     * An admin publishes an affiliate draft in one step (21 Nov 2026), as "Approve and publish"
     * on the URL import does: it is sent for review on the affiliate's behalf and approved. All
     * approval checks are made first, so a draft that is not ready is left as it was.
     */
    public function publishAffiliateDraft(Listing $listing, User $admin): void
    {
        $this->authorize($admin, 'approve', $listing);
        if (! $listing->isAffiliateListing()) {
            throw new ListingWorkflowException('Only an affiliate draft can be published this way. Owners and agents send their own listings for review.');
        }
        $this->expect($listing, [S::Draft], 'Only a draft can be published this way.');

        $missing = ListingChecklist::missingForApproval($listing);
        if ($missing !== []) {
            throw new ListingWorkflowException('This draft cannot be published yet.', $missing);
        }

        DB::transaction(function () use ($listing, $admin) {
            $this->submitOnBehalf($listing, $admin);
            $this->approve($listing->fresh(), $admin);
        });
    }

    public function approve(Listing $listing, User $admin): void
    {
        $this->authorize($admin, 'approve', $listing);
        $this->expect($listing, [S::PendingReview], 'Only a listing waiting for review can be approved.');

        $missing = ListingChecklist::missingForApproval($listing);
        if ($missing !== []) {
            throw new ListingWorkflowException('This listing cannot be approved yet.', $missing);
        }

        $this->move($listing, S::Live, $admin, 'listing.approve', null, function (Listing $l) {
            $l->published_at ??= now();
            $l->rejection_reason = null;
        });

        $this->plans->syncListing($listing);
        $this->notifyLister($listing, 'listing.approve');
    }

    public function reject(Listing $listing, User $admin, string $reason): void
    {
        $this->authorize($admin, 'reject', $listing);
        $this->expect($listing, [S::PendingReview], 'Only a listing waiting for review can be rejected.');
        $reason = $this->requireReason($reason);

        $this->move($listing, S::Rejected, $admin, 'listing.reject', $reason, fn (Listing $l) => $l->rejection_reason = $reason);
        $this->notifyLister($listing, 'listing.reject', $reason);
    }

    /** Lister reopens a rejected listing to edit and send again. */
    public function reopen(Listing $listing, User $actor): void
    {
        $this->authorize($actor, 'submit', $listing);
        $this->expect($listing, [S::Rejected], 'Only a rejected listing can be reopened.');

        $this->move($listing, S::Draft, $actor, 'listing.reopen');
    }

    public function pause(Listing $listing, User $actor, ?string $reason = null): void
    {
        $this->authorize($actor, 'pause', $listing);
        $this->expect($listing, [S::Live], 'Only a live listing can be paused.');
        $reason = $actor->isAdmin() ? $this->requireReason($reason) : $reason;

        $this->move($listing, S::Paused, $actor, 'listing.pause', $reason);
        if ($actor->isAdmin()) {
            $this->notifyLister($listing, 'listing.pause', $reason);
        }
    }

    public function resume(Listing $listing, User $actor): void
    {
        $this->authorize($actor, 'pause', $listing);
        $this->expect($listing, [S::Paused], 'Only a paused listing can be made live again.');
        $this->ensureWithinPlanLimit($listing);

        $this->move($listing, S::Live, $actor, 'listing.resume');
    }

    public function archive(Listing $listing, User $actor, ?string $reason = null): void
    {
        $this->authorize($actor, 'archive', $listing);
        $this->expect($listing, [S::Live, S::Paused], 'Only a live or paused listing can be archived.');
        $reason = $actor->isAdmin() ? $this->requireReason($reason) : $reason;

        $this->move($listing, S::Archived, $actor, 'listing.archive', $reason);
        if ($actor->isAdmin()) {
            $this->notifyLister($listing, 'listing.archive', $reason);
        }
    }

    /** A live listing whose deeplink or licence number changed goes back for a new check. */
    public function sendBackForReview(Listing $listing, ?User $actor, string $why): void
    {
        if ($listing->status !== S::Live) {
            return;
        }

        $this->move($listing, S::PendingReview, $actor, 'listing.back_to_review', $why);
        $this->notifyReviewers($listing);
    }

    /** System pause when a plan ends or shrinks (no user to authorize). */
    public function pauseForPlan(Listing $listing, string $reason): void
    {
        if ($listing->status !== S::Live) {
            return;
        }

        $this->move($listing, S::Paused, null, 'listing.pause_plan_limit', $reason);
        $this->notifyLister($listing, 'listing.pause_plan_limit', $reason);
    }

    /** Pauses a live or waiting listing because its account was deactivated by a super admin. */
    public function pauseForAccount(Listing $listing, User $superAdmin, string $reason): void
    {
        if (! $superAdmin->isSuperAdmin()) {
            throw new ListingWorkflowException('You do not have permission to do that.');
        }
        if (! in_array($listing->status, [S::Live, S::PendingReview], true)) {
            return;
        }

        $this->move($listing, S::Paused, $superAdmin, 'listing.pause_account', $reason);
    }

    /** A super admin accepts an island admin's recommendation to reject. */
    public function rejectOnRecommendation(Listing $listing, User $superAdmin, string $reason): void
    {
        $this->reject($listing, $superAdmin, $reason);
    }

    private function notifyLister(Listing $listing, string $action, ?string $reason = null): void
    {
        $listing->loadMissing('owner');
        $listing->owner?->notify(new \App\Notifications\ListingStatusChanged($listing->id, $action, $reason));
    }

    /** The island's active admins, or every super admin when the island has none. */
    private function notifyReviewers(Listing $listing): void
    {
        $admins = User::active()->where('role', 'island_admin')
            ->whereHas('islands', fn ($q) => $q->where('islands.id', $listing->island_id))
            ->get();

        if ($admins->isEmpty()) {
            $admins = User::active()->where('role', 'super_admin')->get();
        }

        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ListingNeedsReview($listing->id));
    }

    /** @param list<S> $from */
    private function expect(Listing $listing, array $from, string $message): void
    {
        if (! in_array($listing->status, $from, true)) {
            throw new ListingWorkflowException($message);
        }
    }

    private function authorize(User $actor, string $ability, Listing $listing): void
    {
        if (Gate::forUser($actor)->denies($ability, $listing)) {
            throw new ListingWorkflowException('You do not have permission to do that.');
        }
    }

    private function requireReason(?string $reason): string
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new ListingWorkflowException('Please give a reason. It is sent to the lister.');
        }

        return mb_substr($reason, 0, 500);
    }

    /** Counts the account's listings that take a place under its plan, including this one. */
    private function ensureWithinPlanLimit(Listing $listing): void
    {
        $listing->loadMissing('owner');
        if ($listing->isAffiliateListing()) {
            return;   // affiliates have no plan
        }

        $plan = $this->plans->planForListing($listing);
        if ($plan->max_listings === null) {
            return;
        }

        $used = $this->plans->listingsQueryFor($listing)
            ->whereIn('status', [S::PendingReview, S::Live])
            ->whereKeyNot($listing->id)
            ->count();

        if ($used >= $plan->max_listings) {
            throw new ListingWorkflowException(
                "Your {$plan->name} plan allows ".trans_choice(':count listing|:count listings', $plan->max_listings)
                .'. Pause or archive another listing, or choose a bigger plan.'
            );
        }
    }

    private function move(Listing $listing, S $to, ?User $actor, string $action, ?string $reason = null, ?callable $also = null): void
    {
        DB::transaction(function () use ($listing, $to, $actor, $action, $reason, $also) {
            $before = $listing->status->value;
            $listing->status = $to;
            if ($also) {
                $also($listing);
            }
            $listing->save();

            Audit::record($actor, $action, $listing, null, array_filter([
                'before' => ['status' => $before],
                'after' => ['status' => $to->value],
                'reason' => $reason,
            ]));
        });
    }
}
