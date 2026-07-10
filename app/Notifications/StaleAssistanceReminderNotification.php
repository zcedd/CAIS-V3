<?php

namespace App\Notifications;

use App\Models\Assistance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaleAssistanceReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Assistance $assistance,
        private string $monthKey,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'system',
            'title' => 'Stale assistance reminder',
            'message' => sprintf(
                'Assistance #%d has not been updated for at least 7 days and is still open.',
                $this->assistance->id,
            ),
            'assistance_id' => $this->assistance->id,
            'program_id' => $this->assistance->program_id,
            'month_key' => $this->monthKey,
        ];
    }
}
