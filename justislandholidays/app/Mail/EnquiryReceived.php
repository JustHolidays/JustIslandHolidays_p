<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->enquiry->guest_email, $this->enquiry->guest_name)],
            subject: 'New enquiry: '.$this->enquiry->listing->title('en'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.enquiry-received-text',
            markdown: 'mail.enquiry-received',
        );
    }
}
