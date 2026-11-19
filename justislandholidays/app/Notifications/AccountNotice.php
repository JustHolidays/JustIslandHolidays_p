<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Plain notices about an account or licence, with an optional reason. */
class AccountNotice extends Notification implements ShouldQueue
{
    use Queueable;

    private const COPY = [
        'account.deactivated' => ['Your account has been deactivated', 'Your Just Island Holidays account has been deactivated and your listings are paused.'],
        'account.reactivated' => ['Your account is active again', 'Your Just Island Holidays account is active again. Your listings stay paused until you make them live.'],
        'licence.rejected' => ['Your licence number could not be verified', 'We looked up your licence number in the official register and could not verify it, so your listing cannot go live yet. Please check the number and update it in your listing.'],
        'deeplink.message' => ['About your Check availability link', 'Our team has a message about the booking link on one of your listings.'],
        'phone.choose' => ['Do you want your phone number on your listing?', 'Your plan can show your phone number to visitors. You can now choose whether it is shown. Until you choose, it is hidden. To show it, sign in, edit your listing, and choose "Yes, show it publicly" in the Location and contact step.'],
    ];

    public function __construct(public string $kind, public ?string $reason = null, public ?string $about = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $intro] = self::COPY[$this->kind] ?? ['A message from Just Island Holidays', ''];

        $mail = (new MailMessage())->subject($subject)->greeting('Hello '.$notifiable->name);
        if ($intro !== '') {
            $mail->line($intro);
        }
        if ($this->about) {
            $mail->line('Listing: '.$this->about);
        }
        if ($this->reason) {
            $mail->line(($this->kind === 'deeplink.message' ? 'Message: ' : 'Reason: ').$this->reason);
        }

        return $mail->line('If you have a question, reply to this email.');
    }
}
