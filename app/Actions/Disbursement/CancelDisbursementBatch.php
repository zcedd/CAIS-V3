<?php

namespace App\Actions\Disbursement;

use App\Enums\DisbursementBatchStatus;
use App\Models\DisbursementBatch;
use App\Models\User;
use App\Services\Disbursement\DisbursementAudit;
use App\Services\Disbursement\DisbursementBatchWorkflow;
use App\Services\Disbursement\DisbursementNotifier;
use App\Services\Disbursement\DisbursementStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDisbursementBatch
{
    public function __construct(
        private DisbursementBatchWorkflow $workflow,
        private DisbursementStageFlow $stages,
        private DisbursementAudit $audit,
        private DisbursementNotifier $notifier,
    ) {}

    /**
     * @param  array{reason: string, lock_version?: int|null}  $data
     */
    public function __invoke(DisbursementBatch $batch, User $actor, array $data): DisbursementBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DisbursementBatch {
            $batch = DisbursementBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);
            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A cancellation reason is required.',
                ]);
            }

            $this->stages->cancelOpenTasks($batch);
            $updated = $this->workflow->move($batch, DisbursementBatchStatus::Cancelled, $actor, [
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($updated->creator instanceof User) {
                $this->notifier->send(
                    collect([$updated->creator]),
                    $updated,
                    sprintf('disbursement:%d:cancelled', $updated->id),
                    'Disbursement cancelled',
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
