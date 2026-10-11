<?php

namespace App\Console\Commands;

use App\Enums\DispersalBatchStatus;
use App\Enums\PermissionName;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalNotifier;
use Illuminate\Console\Command;

class NotifyUpcomingDispersals extends Command
{
    protected $signature = 'dispersal:notify-upcoming';

    protected $description = 'Remind releasing staff about dispersals scheduled within two days';

    public function handle(DispersalNotifier $notifier): int
    {
        $batches = DispersalBatch::query()
            ->with('department')
            ->where('status', DispersalBatchStatus::Scheduled)
            ->whereBetween('scheduled_at', [now(), now()->addDays(2)])
            ->get();

        foreach ($batches as $batch) {
            $users = User::permission(PermissionName::DispersalExecute->value)
                ->where('department_id', $batch->department_id)
                ->get();

            $notifier->send(
                $users,
                $batch,
                sprintf('dispersal:%d:reminder:%s', $batch->id, $batch->scheduled_at?->toDateString()),
                'Dispersal coming up',
                sprintf('%s is scheduled for %s.', $batch->batch_code, $batch->scheduled_at?->toDateTimeString()),
            );
        }

        $this->info(sprintf('Checked %d upcoming dispersal batches.', $batches->count()));

        return self::SUCCESS;
    }
}
