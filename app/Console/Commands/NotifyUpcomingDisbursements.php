<?php

namespace App\Console\Commands;

use App\Enums\DisbursementBatchStatus;
use App\Enums\PermissionName;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementNotifier;
use Illuminate\Console\Command;

class NotifyUpcomingDisbursements extends Command
{
    protected $signature = 'disbursement:notify-upcoming';

    protected $description = 'Remind releasing staff about disbursements scheduled within two days';

    public function handle(DisbursementNotifier $notifier): int
    {
        $batches = DisbursementBatch::query()
            ->with('department')
            ->where('status', DisbursementBatchStatus::Scheduled)
            ->whereBetween('scheduled_at', [now(), now()->addDays(2)])
            ->get();

        foreach ($batches as $batch) {
            $users = User::permission(PermissionName::DisbursementExecute->value)
                ->where('department_id', $batch->department_id)
                ->get();

            $notifier->send(
                $users,
                $batch,
                sprintf('disbursement:%d:reminder:%s', $batch->id, $batch->scheduled_at?->toDateString()),
                'Disbursement coming up',
                sprintf('%s is scheduled for %s.', $batch->batch_code, $batch->scheduled_at?->toDateTimeString()),
            );
        }

        $this->info(sprintf('Checked %d upcoming disbursement batches.', $batches->count()));

        return self::SUCCESS;
    }
}
