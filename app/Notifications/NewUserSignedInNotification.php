<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserSignedInNotification extends Notification
{
    public function __construct(public User $newUser) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':name signed in to the dashboard', ['name' => $this->newUser->name]))
            ->line(__(':name (:email) signed in with Microsoft for the first time and was given Staff access.', [
                'name' => $this->newUser->name,
                'email' => $this->newUser->email,
            ]))
            ->line(__('Staff can see Software and Hardware only. Give them Manager or Admin if they need more.'))
            ->action(__('Manage users'), route('users'));
    }
}
