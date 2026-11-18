<?php

namespace App\Jobs;

use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a stored enquiry to the listing's contact address, with reply-to set to the
 * visitor so the owner answers from their own inbox. Runs on the queue.
 */
class SendEnquiryEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> seconds between retries */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $enquiryId)
    {
    }

    public function handle(): void
    {
        $enquiry = Enquiry::with(['listing.private', 'listing.translations', 'listing.island', 'listing.area', 'owner'])->find($this->enquiryId);

        if (! $enquiry || $enquiry->email_sent_at !== null || $enquiry->status === 'spam') {
            return;
        }

        // Enquiries go to the listing's contact address; the account email is the fallback.
        $to = $enquiry->listing?->private?->contact_email ?? $enquiry->owner?->email;
        if (! $to) {
            return;
        }

        Mail::to($to)->send(new EnquiryReceived($enquiry));

        $enquiry->forceFill(['email_sent_at' => now()])->save();
    }
}
