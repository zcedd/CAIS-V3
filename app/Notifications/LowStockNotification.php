<?php

namespace App\Notifications;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Item $item,
        private int $onHand,
        private int $threshold,
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
        $departmentSlug = $this->item->department?->slug;
        $url = $departmentSlug !== null
            ? route('user.items.index', ['department' => $departmentSlug])
            : url('/');

        $message = sprintf(
            'Stock of %s is at %d, at or below the threshold of %d.',
            $this->item->name,
            $this->onHand,
            $this->threshold,
        );

        return [
            'category' => 'stock',
            'title' => 'Low stock alert',
            'message' => $message,
            'url' => $url,
            'item_id' => $this->item->id,
            'on_hand' => $this->onHand,
            'threshold' => $this->threshold,
        ];
    }
}
