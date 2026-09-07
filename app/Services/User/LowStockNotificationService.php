<?php

namespace App\Services\User;

use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\DB;

class LowStockNotificationService
{
    public function notifyIfNeeded(?Item $item): void
    {
        if (! $item instanceof Item) {
            return;
        }

        DB::transaction(function () use ($item): void {
            $threshold = $item->low_stock_threshold;

            if ($threshold === null || ! $item->tracksInventory()) {
                return;
            }

            $balance = ItemStockBalance::query()
                ->where('department_id', $item->department_id)
                ->where('item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (! $balance instanceof ItemStockBalance) {
                return;
            }

            $isLow = $balance->on_hand <= $threshold;

            if (! $isLow && $balance->low_stock_notified) {
                $balance->update(['low_stock_notified' => false]);

                return;
            }

            if (! $isLow || $balance->low_stock_notified) {
                return;
            }

            $item->loadMissing('department:id,name,slug');

            $users = User::query()
                ->where('department_id', $item->department_id)
                ->get();

            foreach ($users as $user) {
                $user->notify(new LowStockNotification($item, $balance->on_hand, $threshold));
            }

            $balance->update(['low_stock_notified' => true]);
        });
    }

    public function notifyAll(): int
    {
        $sent = 0;

        ItemStockBalance::query()
            ->with(['item:id,name,kind,department_id,low_stock_threshold', 'item.department:id,name,slug'])
            ->where('low_stock_notified', false)
            ->chunkById(100, function ($balances) use (&$sent): void {
                foreach ($balances as $balance) {
                    $item = $balance->item;

                    if (! $item instanceof Item || ! $item->tracksInventory() || $item->low_stock_threshold === null) {
                        continue;
                    }

                    if ($balance->on_hand > $item->low_stock_threshold) {
                        continue;
                    }

                    $this->notifyIfNeeded($item);
                    $sent++;
                }
            });

        return $sent;
    }
}
