<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Nightly data retention (periods are proposals, set in config/site.php):
 *  - enquiries are deleted 24 months after they were sent;
 *  - raw click events are deleted after 13 months, once their daily totals are saved;
 *  - guest references on booked stays are cleared 24 months after the stay ended;
 *  - accounts soft-deleted more than 30 days ago have their personal data removed.
 *
 * Purged accounts are anonymised rather than removed, because listings, payments and the
 * audit log still refer to them. Their private listing details and licence documents go.
 */
class EnforceRetention extends Command
{
    protected $signature = 'data:retention {--dry-run : Count what would be removed without removing it}';

    protected $description = 'Delete or anonymise data that is past its retention period';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $enquiryCutoff = now()->subMonths((int) config('site.enquiries.retention_months'));
        $clickCutoff = now()->subMonths((int) config('site.clicks.retention_months'))->startOfDay();
        $accountCutoff = now()->subDays((int) config('site.deleted_account_purge_days'));

        // 1. Enquiries
        $enquiries = Enquiry::where('created_at', '<', $enquiryCutoff);
        $enquiryCount = $dry ? $enquiries->count() : $enquiries->delete();

        // 2. Clicks: save the daily totals for the days being removed, then remove whole days.
        $clicks = DB::table('deeplink_clicks')->where('clicked_at', '<', $clickCutoff);
        $clickCount = $clicks->count();
        if (! $dry && $clickCount > 0) {
            $oldest = DB::table('deeplink_clicks')->min('clicked_at');
            RollupClicks::rebuild(\Illuminate\Support\Carbon::parse($oldest), $clickCutoff->copy()->subDay());
            $clicks->delete();
        }

        // 2b. Guest references on booked stays: cleared 24 months after the stay ended (the stay stays booked).
        $refs = DB::table('calendar_bookings')->whereNotNull('guest_ref')
            ->where('check_out', '<', now()->subMonths((int) config('site.enquiries.retention_months'))->toDateString());
        $refCount = $dry ? $refs->count() : $refs->update(['guest_ref' => null]);

        // 3. Accounts deleted more than 30 days ago
        $accounts = User::onlyTrashed()
            ->where('deleted_at', '<', $accountCutoff)
            ->where('email', 'not like', '%@deleted.invalid')
            ->get();
        if (! $dry) {
            $accounts->each(fn (User $u) => $this->purge($u));
        }

        $summary = ['enquiries' => $enquiryCount, 'clicks' => $clickCount, 'guest_refs' => $refCount, 'accounts' => $accounts->count(), 'dry_run' => $dry];
        if (! $dry) {
            Audit::record(null, 'job.retention', 'job', 'data:retention', $summary);
        }

        $this->info(($dry ? 'Would remove: ' : 'Removed: ')."{$enquiryCount} enquiries, {$clickCount} click events, {$refCount} guest references, {$accounts->count()} deleted accounts.");

        return self::SUCCESS;
    }

    private function purge(User $user): void
    {
        DB::transaction(function () use ($user) {
            $listings = Listing::withTrashed()->where('owner_id', $user->id)->with('licence')->get();

            foreach ($listings as $listing) {
                if ($listing->licence?->document_path) {
                    Storage::disk('local')->delete($listing->licence->document_path);
                    $listing->licence->forceFill(['document_path' => null])->save();
                }
                DB::table('listing_private')->where('listing_id', $listing->id)->delete();
                if (! $listing->trashed()) {
                    $listing->delete();
                }
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('island_admins')->where('user_id', $user->id)->delete();
            \App\Support\AffiliateLogo::delete($user->affiliate_logo_path);

            $user->forceFill([
                'name' => 'Deleted account',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'phone' => null,
                'password' => Str::password(64),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'affiliate_data' => null,
                'affiliate_logo_path' => null,
                'affiliate_logo_alt' => null,
                'affiliate_website_url' => null,
                'affiliate_booking_notes' => null,
                'marketing_consent' => false,
                'consented_at' => null,
                'closure_reason' => null,   // their own words about closing (20 Nov 2026)
            ])->saveQuietly();
            DB::table('favourites')->where('user_id', $user->id)->delete();

            Audit::record(null, 'user.purge', 'user', $user->id, ['listings' => $listings->count()]);
        });
    }
}
