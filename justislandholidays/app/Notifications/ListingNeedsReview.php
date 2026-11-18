<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the island's admins (or super admins if the island has none): a listing is waiting for review. */
class ListingNeedsReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $listingId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $listing = Listing::with(['translations', 'island', 'licence'])->find($this->listingId);

        $mail = (new MailMessage())
            ->subject('Listing to review: '.($listing?->title('en') ?? 'new listing'))
            ->line('A listing on '.($listing?->island?->translate('name', 'en') ?? 'your island').' is waiting for review. The target is 48 hours.');

        if ($listing?->licence?->status === 'pending') {
            $mail->line('It also has a licence number to check.');
        }

        return $mail->action('Review the listing', route('admin.listings.show', $this->listingId));
    }
}
