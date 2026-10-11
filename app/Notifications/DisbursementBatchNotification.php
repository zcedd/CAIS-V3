<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DisbursementBatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $dedupeKey,
        private string $title,
        private string $message,
        private ?string $url,
        private int $disbursementBatchId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'system',
            'dedupe_key' => $this->dedupeKey,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'disbursement_batch_id' => $this->disbursementBatchId,
        ];
    }
}
