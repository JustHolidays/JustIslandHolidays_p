<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the lister their listing was approved, rejected, paused or archived, with the reason. */
class ListingStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    private const COPY = [
        'listing.approve' => ['Your listing is live', 'Good news: your listing has been checked and is now live on Just Island Holidays.'],
        'listing.reject' => ['Your listing was not approved', 'We checked your listing and could not approve it.'],
        'listing.pause' => ['Your listing has been paused', 'Our team has paused your listing, so visitors cannot see it for now.'],
        'listing.archive' => ['Your listing has been archived', 'Our team has archived your listing and removed it from the site.'],
        'listing.pause_plan_limit' => ['One of your listings has been paused', 'Your plan now allows fewer listings, so we have paused this one. Nothing has been deleted.'],
        'listing.pause_account' => ['Your listing has been paused', 'Your account has been deactivated, so your listing is paused.'],
    ];

    public function __construct(public int $listingId, public string $action, public ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $listing = Listing::withTrashed()->with('translations')->find($this->listingId);
        [$subject, $intro] = self::COPY[$this->action] ?? ['Your listing has changed', 'There has been a change to your listing.'];

        $mail = (new MailMessage())
            ->subject($subject.': '.($listing?->title('en') ?? 'your listing'))
            ->greeting('Hello '.$notifiable->name)
            ->line($intro);

        if ($this->reason) {
            $mail->line('Reason: '.$this->reason);
        }

        return $mail
            ->action('Open your listings', route('account.listings.index'))
            ->line('If you have a question, reply to this email.');
    }
}
