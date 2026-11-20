<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An owner or agent asks for a plan upgrade from Plan and billing (20 Nov 2026). Sent to every
 * super admin, with reply-to set to the person asking. Nothing is stored apart from the audit log.
 */
class PlanUpgradeRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $requester,
        public string $currentPlan,
        public string $wanted,
        public ?string $note,
        public int $listingsHeld,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->requester->email, $this->requester->name)],
            subject: 'Plan upgrade request: '.$this->requester->name.' would like '.$this->wanted,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.plan-upgrade-requested-text',
            markdown: 'mail.plan-upgrade-requested',
        );
    }
}
