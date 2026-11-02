<?php

namespace App\Notifications;

use App\Models\BusinessInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public BusinessInvitation $invitation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->invitation->business;
        $inviter = $this->invitation->inviter->name ?? 'A teammate';
        $expires = $this->invitation->expires_at
            ->timezone(config('documind.display_timezone'))
            ->format('j M Y');

        return (new MailMessage)
            ->subject("You're invited to join {$business->name} on DocuMind")
            ->greeting('Hello!')
            ->line("{$inviter} has invited you to join {$business->name} as a {$this->invitation->role->label()}.")
            ->line('Log in, or create a DocuMind account using this email address, then accept the invitation.')
            ->action('Accept invitation', route('invitations.accept', $this->invitation->token))
            ->line("This invitation expires on {$expires}.");
    }
}
