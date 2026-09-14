<?php

namespace App\Console\Commands;

use App\Services\User\LowStockNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stock:notify-low')]
#[Description('Send database reminders for items at or below their low-stock threshold')]
class NotifyLowStock extends Command
{
    public function handle(LowStockNotificationService $lowStockNotificationService): int
    {
        $sentCount = $lowStockNotificationService->notifyAll();

        $this->info("Dispatched {$sentCount} low-stock notification(s).");

        return self::SUCCESS;
    }
}
