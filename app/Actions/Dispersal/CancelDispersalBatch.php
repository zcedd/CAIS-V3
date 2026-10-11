<?php

namespace App\Actions\Dispersal;

use App\Enums\DispersalBatchStatus;
use App\Models\DispersalBatch;
use App\Models\User;
use App\Services\Dispersal\DispersalAudit;
use App\Services\Dispersal\DispersalBatchWorkflow;
use App\Services\Dispersal\DispersalNotifier;
use App\Services\Dispersal\DispersalStageFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDispersalBatch
{
    public function __construct(
        private DispersalBatchWorkflow $workflow,
        private DispersalStageFlow $stages,
        private DispersalAudit $audit,
        private DispersalNotifier $notifier,
    ) {}

    /**
     * @param  array{reason: string, lock_version?: int|null}  $data
     */
    public function __invoke(DispersalBatch $batch, User $actor, array $data): DispersalBatch
    {
        return DB::transaction(function () use ($batch, $actor, $data): DispersalBatch {
            $batch = DispersalBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->workflow->assertLock($batch, isset($data['lock_version']) ? (int) $data['lock_version'] : null);
            $reason = trim($data['reason']);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A cancellation reason is required.',
                ]);
            }

            $this->stages->cancelOpenTasks($batch);
            $updated = $this->workflow->move($batch, DispersalBatchStatus::Cancelled, $actor, [
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($updated->creator instanceof User) {
                $this->notifier->send(
                    collect([$updated->creator]),
                    $updated,
                    sprintf('dispersal:%d:cancelled', $updated->id),
                    'Dispersal cancelled',
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
