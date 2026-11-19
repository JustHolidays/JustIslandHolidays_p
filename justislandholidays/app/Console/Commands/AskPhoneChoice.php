<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\AccountNotice;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * One-off, run by hand after the show_phone change is deployed (19 Nov 2026): emails each
 * owner or agent who has a phone number on a listing that a paid plan could show, but has
 * not chosen to show it, asking them to choose. Each person gets one email, however many
 * listings they have. Run with --dry-run first to see who would be emailed.
 *
 *   php artisan listings:ask-phone-choice --dry-run
 *   php artisan listings:ask-phone-choice
 */
class AskPhoneChoice extends Command
{
    protected $signature = 'listings:ask-phone-choice {--dry-run : List who would be emailed, without sending}';

    protected $description = 'Email owners on paid plans to choose whether their phone number is shown';

    public function handle(): int
    {
        $plans = Plan::where('show_direct_contact', true)->pluck('code');

        $listings = Listing::whereIn('plan_code', $plans)
            ->whereIn('status', ['live', 'paused', 'pending_review', 'draft'])
            ->whereHas('private', fn ($q) => $q->whereNotNull('contact_phone')->where('contact_phone', '!=', '')->where('show_phone', false))
            ->whereHas('owner', fn ($q) => $q->whereIn('role', ['owner', 'agent'])->whereNull('deactivated_at'))
            ->with(['owner', 'translations'])
            ->get();

        $people = $listings->groupBy('owner_id');
        foreach ($people as $ownerListings) {
            /** @var User $owner */
            $owner = $ownerListings->first()->owner;
            $titles = $ownerListings->map(fn (Listing $l) => $l->title('en'))->implode(', ');
            $this->line(($this->option('dry-run') ? 'Would email ' : 'Emailing ').$owner->email.' ('.$titles.')');

            if (! $this->option('dry-run')) {
                $owner->notify(new AccountNotice('phone.choose', null, $titles));
            }
        }

        if (! $this->option('dry-run')) {
            Audit::record(null, 'job.ask_phone_choice', 'job', 'listings:ask-phone-choice', ['emailed' => $people->count()]);
        }
        $this->info(($this->option('dry-run') ? 'Would email ' : 'Emailed ').$people->count().' owner(s) or agent(s).');

        return self::SUCCESS;
    }
}
