<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit code an admin types in to finish signing in. Never queued. */
class SignInCode extends Mailable
{
    public function __construct(public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        // The code stays out of the subject, so it does not show on a locked phone screen.
        return new Envelope(subject: 'Your Just Island Holidays sign-in code');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.sign-in-code-text');
    }
}
