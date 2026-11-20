<?php

namespace App\Admin;

use App\Billing\AccountPlan;
use App\Billing\PayPalClient;
use App\Billing\PayPalException;
use App\Billing\SubscriptionUpdater;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Mail\AccountClosureRequested;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Account closure requests (20 Nov 2026). Owners, agents and visitors ask with "Close my
 * account"; super admins are emailed and the request waits on Settings › Account closures.
 * Closing signs them out, cancels their own PayPal plan, archives their listings and deletes
 * the account; data:retention removes the personal details 30 days later. Declining clears
 * the request and emails the reason. Everything is audited.
 */
final class AccountClosure
{
    /** Roles that can ask to close their own account. */
    public const ROLES = [Role::Owner, Role::Agent, Role::Visitor];

    public function __construct(
        private AccountStatus $status,
        private AccountPlan $plans,
        private PayPalClient $paypal,
        private SubscriptionUpdater $updater,
    ) {
    }

    public static function canRequest(User $user): bool
    {
        return in_array($user->role, self::ROLES, true);
    }

    public function request(User $user, ?string $reason): void
    {
        abort_unless(self::canRequest($user), 403);

        $user->forceFill(['closure_requested_at' => now(), 'closure_reason' => $reason])->save();
        Audit::record($user, 'user.closure_request', $user, null, ['reason_given' => $reason !== null]);

        User::where('role', Role::SuperAdmin)->whereNull('deactivated_at')->whereNotNull('email_verified_at')
            ->pluck('email')
            ->each(fn (string $email) => Mail::to($email)->queue(new AccountClosureRequested($user)));
    }

    public function withdraw(User $user): void
    {
        $user->forceFill(['closure_requested_at' => null, 'closure_reason' => null])->save();
        Audit::record($user, 'user.closure_withdraw', $user);
    }

    public function decline(User $user, User $admin, string $reason): void
    {
        abort_unless($admin->isSuperAdmin(), 403);

        $user->forceFill(['closure_requested_at' => null, 'closure_reason' => null])->save();
        Audit::record($admin, 'user.closure_decline', $user, null, ['reason' => $reason]);
        $user->notify(new AccountNotice('account.closure_declined', $reason));
    }

    public function close(User $user, User $admin): void
    {
        abort_unless($admin->isSuperAdmin() && self::canRequest($user) && $user->closure_requested_at !== null, 403);

        // Their own paid plan stops renewing first; if PayPal cannot be reached, nothing changes.
        $account = $this->plans->accountFor($user);
        $subscription = $account instanceof User ? $this->plans->currentSubscription($account) : null;
        if ($subscription && $subscription->status === 'active' && $subscription->paypal_subscription_id) {
            try {
                $this->paypal->cancelSubscription($subscription->paypal_subscription_id, 'Account closed at the customer\'s request');
            } catch (PayPalException) {
                throw ValidationException::withMessages(['close' => 'PayPal could not be reached to cancel their plan, so the account was not closed. Try again, or cancel the plan in PayPal first.']);
            }
            $this->updater->apply($subscription, ['id' => $subscription->paypal_subscription_id, 'status' => 'CANCELLED']);
        }

        // Tell them before the account is gone (sent now, not queued).
        $user->notifyNow(new AccountNotice('account.closed'));

        DB::transaction(function () use ($user, $admin) {
            $archived = 0;
            Listing::where('owner_id', $user->id)->where('status', '!=', ListingStatus::Archived->value)->get()
                ->each(function (Listing $listing) use ($admin, &$archived) {
                    $before = $listing->status->value;
                    $listing->forceFill(['status' => ListingStatus::Archived])->save();
                    Audit::record($admin, 'listing.archive', $listing, null, ['before' => $before, 'reason' => 'Account closed at the lister\'s request']);
                    $archived++;
                });

            $this->status->endSessions($user);
            $user->forceFill(['closure_requested_at' => null])->save();
            $user->delete();   // soft delete: personal details are purged 30 days later
            Audit::record($admin, 'user.close', $user, null, ['role' => $user->role?->value, 'listings_archived' => $archived]);
        });
    }
}
