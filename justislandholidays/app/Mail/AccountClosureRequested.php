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

/** Someone pressed "Close my account" (20 Nov 2026): to every super admin, reply-to that person. */
class AccountClosureRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $requester)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->requester->email, $this->requester->name)],
            subject: 'Account closure request: '.$this->requester->name,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.account-closure-requested-text', markdown: 'mail.account-closure-requested');
    }
}
