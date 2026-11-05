<?php

namespace App\Notifications;

use App\Models\TeacherAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

class StaffPasswordReset extends Notification
{
    public function __construct(#[SensitiveParameter] public readonly string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(TeacherAccount $notifiable): MailMessage
    {
        $path = route('staff.password.reset', ['token' => $this->token, 'email' => $notifiable->email], absolute: false);

        return (new MailMessage)
            ->subject('Reset your NGF_C++CodeQuest staff password')
            ->line('Use this school email as your username when you sign in.')
            ->action('Choose a new staff password', rtrim(config('app.url'), '/').$path)
            ->line('This link expires in 30 minutes and works once.')
            ->line('If you did not request this link, you can ignore this message.');
    }
}
