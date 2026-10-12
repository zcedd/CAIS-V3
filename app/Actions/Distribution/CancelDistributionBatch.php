<?php

namespace App\Actions\Distribution;

use App\Enums\DistributionBatchStatus;
use App\Models\DistributionBatch;
use App\Models\User;
use App\Services\Distribution\DistributionAudit;
use App\Services\Distribution\DistributionBatchWorkflow;
use App\Services\Distribution\DistributionNotifier;
use App\Services\Distribution\DistributionStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDistributionBatch
{
    public function __construct(
        private DistributionBatchWorkflow $workflow,
        private DistributionStageFlow $stages,
        private DistributionAudit $audit,
        private DistributionNotifier $notifier,
    ) {}

    /**
     * @param  array{reason: string, lock_version?: int|null}  $data
     */
    public function __invoke(DistributionBatch $batch, User $actor, array $data): DistributionBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DistributionBatch {
            $batch = DistributionBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);
            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A cancellation reason is required.',
                ]);
            }

            $this->stages->cancelOpenTasks($batch);
            $updated = $this->workflow->move($batch, DistributionBatchStatus::Cancelled, $actor, [
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($updated->creator instanceof User) {
                $this->notifier->send(
                    collect([$updated->creator]),
                    $updated,
                    sprintf('distribution:%d:cancelled', $updated->id),
                    'Distribution cancelled',
                    sprintf('%s was cancelled.', $updated->batch_code),
                );
            }

            $this->audit->record($actor, $updated, 'cancelled', [
                'revision' => $updated->revision,
                'reason' => $reason,
            ]);

            return $updated;
        });
    }
}
