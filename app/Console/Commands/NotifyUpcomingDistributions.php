<?php

namespace App\Console\Commands;

use App\Enums\DistributionBatchStatus;
use App\Enums\PermissionName;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionNotifier;
use Illuminate\Console\Command;

class NotifyUpcomingDistributions extends Command
{
    protected $signature = 'distribution:notify-upcoming';

    protected $description = 'Remind releasing staff about distributions scheduled within two days';

    public function handle(DistributionNotifier $notifier): int
    {
        $batches = DistributionBatch::query()
            ->with('department')
            ->where('status', DistributionBatchStatus::Scheduled)
            ->whereBetween('scheduled_at', [now(), now()->addDays(2)])
            ->get();

        foreach ($batches as $batch) {
            $users = User::permission(PermissionName::DistributionExecute->value)
                ->where('department_id', $batch->department_id)
                ->get();

            $notifier->send(
                $users,
                $batch,
                sprintf('distribution:%d:reminder:%s', $batch->id, $batch->scheduled_at?->toDateString()),
                'Distribution coming up',
                sprintf('%s is scheduled for %s.', $batch->batch_code, $batch->scheduled_at?->toDateTimeString()),
            );
        }

        $this->info(sprintf('Checked %d upcoming distribution batches.', $batches->count()));

        return self::SUCCESS;
    }
}
