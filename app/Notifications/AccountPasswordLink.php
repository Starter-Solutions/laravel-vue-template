<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AccountPasswordLink extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly bool $invitation,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $appName = config()->string('app.name');
        $broker = config()->string('fortify.passwords');
        $expiresInMinutes = config()->integer("auth.passwords.{$broker}.expire");
        $url = URL::temporarySignedRoute('account-password.edit', now()->addMinutes($expiresInMinutes), [
            'user' => $notifiable->getKey(),
            'email' => $notifiable->email,
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject($this->invitation ? "You're invited to {$appName}" : 'Reset your password')
            ->line($this->invitation
                ? "An account has been created for you on {$appName}."
                : "An administrator requested a password reset for your {$appName} account.")
            ->action($this->invitation ? 'Set your password' : 'Reset password', $url)
            ->line("This link expires in {$expiresInMinutes} minutes.");
    }
}
