<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitation for a new admin or affiliate: a link to set a password, valid for 7 days. */
class Invitation extends Notification
{
    use Queueable;

    public function __construct(public string $token, public string $roleLabel)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject('You have been invited to Just Island Holidays')
            ->greeting('Hello '.$notifiable->name)
            ->line("You have been given a {$this->roleLabel} account on Just Island Holidays.")
            ->action('Set your password', route('invitation.show', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('This link works for 7 days.');

        if (in_array($this->roleLabel, ['Island admin', 'Super admin'], true)) {
            $mail->line('After setting your password you will set up two-factor sign-in with an authenticator app on your phone.');
        }

        return $mail;
    }
}
