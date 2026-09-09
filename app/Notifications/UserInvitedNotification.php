<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitedNotification extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expireMinutes = (int) config('auth.passwords.users.expire');

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'invite' => 1,
        ], false));

        $mail = (new MailMessage)
            ->subject('Set your CAIS password');

        $firstName = trim((string) $notifiable->firstName);

        if ($firstName !== '') {
            $mail->greeting('Hello '.$firstName.',');
        }

        return $mail
            ->line('An account was created for you. Use the button below to choose a password.')
            ->action('Set password', $url)
            ->line("This link expires in {$expireMinutes} minutes. After that, use Forgot password on the login page.");
    }
}
