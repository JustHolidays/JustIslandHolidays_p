<?php

namespace App\Admin;

use App\Enums\Role;
use App\Listings\ListingWorkflow;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Deactivating an account: blocks sign-in, ends its sessions, pauses its listings,
 * emails the reason and writes the audit log. Super admins only.
 */
final class AccountStatus
{
    public function __construct(private ListingWorkflow $workflow)
    {
    }

    public function deactivate(User $target, User $actor, string $reason): void
    {
        if (Gate::forUser($actor)->denies('deactivate', $target)) {
            throw ValidationException::withMessages(['reason' => $actor->id === $target->id
                ? 'You cannot deactivate your own account.'
                : 'This account cannot be deactivated. The last active super admin must stay active.']);
        }

        DB::transaction(function () use ($target, $actor, $reason) {
            $target->forceFill(['deactivated_at' => now()])->save();
            $this->endSessions($target);

            if ($target->role?->isLister()) {
                Listing::where('owner_id', $target->id)->get()
                    ->each(fn (Listing $l) => $this->workflow->pauseForAccount($l, $actor, $reason));
            }

            Audit::record($actor, 'user.deactivate', $target, null, ['reason' => $reason, 'role' => $target->role?->value]);
        });

        $target->notify(new AccountNotice('account.deactivated', $reason));
    }

    public function reactivate(User $target, User $actor): void
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $target->forceFill(['deactivated_at' => null])->save();
        Audit::record($actor, 'user.reactivate', $target);

        if ($target->role?->isLister()) {
            $target->notify(new AccountNotice('account.reactivated'));
        }
    }

    /** Signs the account out everywhere: database sessions and "keep me signed in" cookies. */
    public function endSessions(User $target): void
    {
        DB::table('sessions')->where('user_id', $target->id)->delete();
        $target->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    public static function isStaff(User $user): bool
    {
        return in_array($user->role, [Role::IslandAdmin, Role::SuperAdmin], true);
    }
}
